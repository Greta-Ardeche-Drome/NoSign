import time
import serial
import mysql.connector
from datetime import datetime
from smartcard.System import readers
from smartcard.util import toHexString

# Configuration de la base de données
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'nosign',
    'port': 3307,
    'charset': 'utf8mb4',
    'collation': 'utf8mb4_general_ci'
}

# Connexion au lecteur RFID
r = readers()
if len(r) == 0:
    print("Aucun lecteur RFID trouvé.")
    exit()
reader = r[0]
connection = reader.createConnection()

# Connexion au capteur d'empreintes digitales (Arduino)
ser = serial.Serial('COM10', 9600, timeout=1)  # Timeout plus court
time.sleep(2)  # Attendre que la connexion soit établie

def clear_serial_buffer():
    """Vide le buffer série"""
    ser.reset_input_buffer()
    ser.reset_output_buffer()
    time.sleep(0.1)

def get_fingerprint():
    """
    Attend qu'une empreinte valide soit détectée par le capteur d'empreintes.
    Renvoie l'ID de l'empreinte si valide, sinon None.
    """
    print("🟡 En attente d'une empreinte digitale... (Pose ton doigt)")
    
    # Vider les buffers
    clear_serial_buffer()
    
    # Attendre que l'Arduino demande de choisir une option
    wait_start = time.time()
    option_prompt_received = False
    
    while time.time() - wait_start < 5:  # 5 secondes maximum
        if ser.in_waiting > 0:
            response = ser.readline().decode().strip()
            print(f"📡 Arduino dit: {response}")
            
            if "Please choose an option" in response:
                option_prompt_received = True
                break
        time.sleep(0.1)
    
    if not option_prompt_received:
        print("⚠️ L'Arduino ne demande pas d'option. Tentative d'envoi direct...")
    
    # Envoyer l'option 2 (format utilisé dans le code fonctionnel)
    print("📤 Envoi de l'option 2 à l'Arduino...")
    ser.write(b"2\n")
    time.sleep(0.5)
    
    # Lire confirmation
    confirmation_received = False
    for _ in range(5):  # Lire jusqu'à 5 lignes
        if ser.in_waiting > 0:
            response = ser.readline().decode().strip()
            print(f"📡 Réponse: {response}")
            if "Image taken" in response or "Found ID" in response:
                confirmation_received = True
    
    if not confirmation_received:
        print("⚠️ Pas de confirmation de l'Arduino. Tentative alternative...")
        # Essai alternatif avec caractère retour chariot
        ser.write(b"2\r\n")
        time.sleep(0.5)
    
    # Commencer à lire les réponses pour une empreinte
    timeout = time.time() + 20  # Temps max d'attente de 20s
    
    while time.time() < timeout:
        if ser.in_waiting > 0:
            response = ser.readline().decode().strip()
            print(f"📡 Arduino: {response}")
            
            # Vérifier les différentes réponses possibles
            if "Found ID #" in response:
                try:
                    # Extraire l'ID de l'empreinte (différentes formats possibles)
                    if "#" in response:
                        parts = response.split("#")
                        if len(parts) > 1:
                            id_part = parts[1].split(" ")[0]
                            fingerprint_id = int(id_part)
                            print(f"✅ Empreinte détectée ! ID: {fingerprint_id}")
                            return fingerprint_id
                except Exception as e:
                    print(f"⚠️ Erreur lors de l'extraction de l'ID: {e}")
            
            elif "No finger detected" in response:
                # Normal, continue à attendre
                pass
            elif "Did not find a match" in response:
                print("❌ Empreinte non reconnue. Réessayez.")
            elif "Communication error" in response:
                print("⚠️ Erreur de communication avec le capteur.")
        
        time.sleep(0.1)  # Petite pause pour éviter de saturer le CPU
    
    print("🕒 Temps d'attente dépassé, aucune empreinte détectée.")
    return None

def add_scan(uid, fingerprint_id):
    try:
        db = mysql.connector.connect(**DB_CONFIG)
        cursor = db.cursor()
        
        # Vérifier si l'UID existe et récupérer l'empreinte associée
        cursor.execute("SELECT fingerprintid FROM etudiants WHERE uid = %s", (uid,))
        result = cursor.fetchone()

        if result is None:
            print(f"🆕 Nouvel UID détecté : {uid}. Non enregistré dans la base de données.")
            return

        expected_fingerprint_id = result[0]
        
        if fingerprint_id == expected_fingerprint_id:
            now = datetime.now()
            date_scan = now.strftime("%Y-%m-%d")
            heure_scan = now.strftime("%H:%M:%S")
            
            cursor.execute(
                "INSERT INTO presences (uid, date_scan, heure_scan) VALUES (%s, %s, %s)",
                (uid, date_scan, heure_scan)
            )
            db.commit()
            print(f"✅ Présence validée pour UID: {uid}, Empreinte: {fingerprint_id}")
        else:
            print("❌ Empreinte digitale incorrecte !")

        cursor.close()
        db.close()
    
    except mysql.connector.Error as err:
        print(f"❌ Erreur de connexion à la base de données : {err}")

def restart_arduino():
    """Tente de redémarrer la communication avec l'Arduino"""
    print("🔄 Réinitialisation de la communication avec l'Arduino...")
    clear_serial_buffer()
    
    # Lire les messages en attente
    while ser.in_waiting > 0:
        response = ser.readline().decode().strip()
        print(f"🧹 Nettoyage buffer: {response}")
    
    # Attendre le prompt "Ready to enroll"
    wait_end = time.time() + 5
    while time.time() < wait_end:
        if ser.in_waiting > 0:
            response = ser.readline().decode().strip()
            print(f"🔄 Arduino: {response}")
            if "Ready to enroll" in response:
                print("✅ Arduino prêt pour le prochain scan.")
                return True
        time.sleep(0.1)
    
    print("⚠️ Arduino non réinitialisé, mais on continue...")
    return False

# Initialisation
print("🔄 Attente de l'initialisation de l'Arduino...")
time.sleep(3)  # Attendre plus longtemps au démarrage

# Vider les messages d'initialisation
timeout_init = time.time() + 10
while time.time() < timeout_init and ser.in_waiting > 0:
    response = ser.readline().decode().strip()
    print(f"🔄 Arduino: {response}")
    if "Ready to enroll or recognize" in response:
        print("✅ Arduino initialisé avec succès")
        break
    time.sleep(0.1)

clear_serial_buffer()
print("🟢 Système prêt. En attente d'une carte RFID...")

while True:
    try:
        connection.connect()
        GET_UID_CMD = [0xFF, 0xCA, 0x00, 0x00, 0x00]
        response, sw1, sw2 = connection.transmit(GET_UID_CMD)

        if sw1 == 0x90 and sw2 == 0x00:
            uid = toHexString(response).replace(" ", "")
            print(f"📡 UID détecté : {uid}")
            
            print("🛑 Veuillez poser votre doigt sur le capteur d'empreintes...")

            # Attente de l'empreinte avec timeout
            fingerprint_id = get_fingerprint()

            if fingerprint_id is not None:
                add_scan(uid, fingerprint_id)
            else:
                print("❌ Aucun scan d'empreinte détecté.")
            
            # Réinitialiser l'Arduino pour le prochain scan
            restart_arduino()
        
        time.sleep(1)

    except Exception as e:
        print(f"🚨 Erreur lors de la lecture RFID ou empreinte digitale: {e}")
        time.sleep(1)