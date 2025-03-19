

#DOIT CHOISIR LE CHOIX 2 DE L'ARDUINO



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
ser = serial.Serial('COM10', 9600, timeout=2)  # Vérifie que COM10 est correct

def get_fingerprint():
    """
    Attend qu'une empreinte valide soit détectée par le capteur d'empreintes.
    Renvoie l'ID de l'empreinte si valide, sinon None.
    """
    ser.flush()  # Nettoie le buffer avant de commencer
    print("🟡 En attente d'une empreinte digitale... (Pose ton doigt)")

    timeout = time.time() + 10  # Temps max d'attente de 10s

    while time.time() < timeout:
        ser.write(b'CHECK\n')  # Envoi de la commande
        time.sleep(0.5)  # Petite pause avant lecture

        response = ser.readline().decode().strip()  # Lecture du retour série
        print(f"📡 Réponse du capteur : {response}")  # Debug pour voir la réponse

        if response.startswith("Found ID #"):
            fingerprint_id = int(response.split("#")[1].split(" ")[0])
            print(f"✅ Empreinte détectée ! ID: {fingerprint_id}")
            return fingerprint_id
        elif response == "NO MATCH":
            print("❌ Empreinte non reconnue, veuillez réessayer...")
        elif response == "ERROR":
            print("⚠️ Erreur du capteur, recommencez.")
        
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
        
        time.sleep(1)

    except Exception as e:
        print("🚨 Erreur lors de la lecture RFID ou empreinte digitale...")
        time.sleep(1)
