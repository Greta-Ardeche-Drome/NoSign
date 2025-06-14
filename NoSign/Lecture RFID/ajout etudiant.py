import csv
import time
import mysql.connector
from datetime import datetime
from smartcard.System import readers
from smartcard.util import toHexString
import serial

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

# Configuration du port série pour l'empreinte digitale
arduino_port = 'COM5'
baud_rate = 9600
ser = serial.Serial(arduino_port, baud_rate, timeout=1)
time.sleep(2)

def add_student_to_db(nom, prenom, uid, fingerprintid=None):
    try:
        db = mysql.connector.connect(**DB_CONFIG)
        cursor = db.cursor()

        cursor.execute("SELECT id FROM etudiants WHERE uid = %s", (uid,))
        result = cursor.fetchone()

        if result is None:
            print(f"🆕 Nouvel étudiant détecté : {nom} {prenom}, UID : {uid}. Ajout dans la table 'etudiants'...")
            cursor.execute("INSERT INTO etudiants (nom, prenom, uid, fingerprintid) VALUES (%s, %s, %s, %s)", 
                           (nom, prenom, uid, fingerprintid))
            db.commit()
            student_id = cursor.lastrowid
            print(f"✅ Étudiant ajouté avec succès. ID de l'étudiant : {student_id}")
        else:
            print(f"✅ Étudiant déjà présent, mise à jour de l'empreinte pour l'UID : {uid}")
            cursor.execute("UPDATE etudiants SET fingerprintid = %s WHERE uid = %s", (fingerprintid, uid))
            db.commit()
            print(f"✅ Empreinte mise à jour avec succès pour l'UID {uid}.")

        cursor.close()
        db.close()

    except mysql.connector.Error as err:
        print(f"❌ Erreur de connexion à la base de données : {err}")

def clear_serial_buffer():
    """Vide le buffer série pour éviter les messages résiduels"""
    while ser.in_waiting > 0:
        ser.readline()

def wait_for_response(timeout_seconds=30):
    """Attend une réponse du capteur avec timeout"""
    start_time = time.time()
    while time.time() - start_time < timeout_seconds:
        if ser.in_waiting > 0:
            response = ser.readline().decode().strip()
            if response:  # Ignore les lignes vides
                return response
        time.sleep(0.1)
    return None

def enroll_fingerprint(finger_id):
    print(f"📌 Début de l'enrôlement de l'empreinte pour l'ID {finger_id}")
    
    # Vider le buffer série
    clear_serial_buffer()
    
    # Envoyer "1" pour entrer en mode enregistrement
    ser.write("1\n".encode())
    time.sleep(1)

    # Attendre que le capteur demande l'ID (avec timeout)
    timeout = time.time() + 15  # 15 secondes de timeout
    id_sent = False
    
    while time.time() < timeout:
        response = wait_for_response(5)
        if response is None:
            print("⏰ Timeout - Aucune réponse du capteur")
            continue
            
        print(f"🔄 Réponse du capteur: '{response}'")

        if "Enter the ID" in response or "Enter ID" in response:
            ser.write(f"{finger_id}\n".encode())
            print(f"✅ ID de l'empreinte envoyé: {finger_id}")
            id_sent = True
            break
        elif "Please choose an option" in response:
            # Le capteur est prêt, renvoyer "1"
            ser.write("1\n".encode())
            time.sleep(1)

    if not id_sent:
        print("❌ Impossible d'envoyer l'ID - timeout")
        return False

    # Attendre la confirmation de l'ID et l'enrôlement
    enrollment_started = False
    enrollment_complete = False
    timeout = time.time() + 60  # 60 secondes pour tout le processus d'enrôlement
    
    while time.time() < timeout and not enrollment_complete:
        response = wait_for_response(5)
        if response is None:
            continue
            
        print(f"🔄 Réponse du capteur: '{response}'")

        # Vérifications pour s'assurer que l'ID correct est utilisé
        if f"Enrolling ID #{finger_id}" in response:
            print(f"✅ Confirmation : L'ID {finger_id} est bien pris en compte.")
            enrollment_started = True
        elif "Enrolling ID #1" in response and finger_id != 1:
            print("⚠️ ERREUR : Le capteur enregistre sous ID #1 au lieu de l'ID demandé !")
            return False

        # Messages pendant l'enrôlement
        elif "Place your finger" in response:
            print("👆 Placez votre doigt sur le capteur")
        elif "Remove finger" in response:
            print("✋ Retirez votre doigt")
        elif "Image taken" in response:
            print("📸 Image capturée")
        elif "Fingerprint did not match" in response:
            print("⚠️ Les empreintes ne correspondent pas. Continuez...")
        elif "Please try again" in response:
            print("🔄 Veuillez réessayer")
        
        # Conditions de fin
        elif "Stored!" in response or "Successfully enrolled" in response:
            print(f"✅ Empreinte ID {finger_id} enregistrée avec succès !")
            enrollment_complete = True
            return True
        elif "Failed to enroll" in response or "Enrollment failed" in response:
            print("❌ Échec de l'enrôlement")
            return False
        elif "Please choose an option" in response and enrollment_started:
            print("✅ Enrôlement terminé - retour au menu principal")
            enrollment_complete = True
            return True

    if not enrollment_complete:
        print("⏰ Timeout pendant l'enrôlement")
        return False

    return False

# Boucle principale
while True:
    print("\n" + "="*50)
    print("Veuillez poser la carte étudiante sur le lecteur avant la saisie des informations !!")
    nom = input("Entrez le nom : ")
    prenom = input("Entrez le prénom : ")

    try:
        connection.connect()
        GET_UID_CMD = [0xFF, 0xCA, 0x00, 0x00, 0x00]
        response, sw1, sw2 = connection.transmit(GET_UID_CMD)

        if sw1 == 0x90 and sw2 == 0x00:
            uid = toHexString(response).replace(" ", "")
            print(f"📡 UID détecté : {uid}")
            
            # Enregistrer les informations de l'étudiant dans la base de données (sans empreinte)
            add_student_to_db(nom, prenom, uid)
            
            # Demander l'enregistrement de l'empreinte digitale
            try:
                finger_id = int(input("Entrez l'ID (1-127) de l'empreinte à enregistrer: "))
                if 1 <= finger_id <= 127:
                    print(f"\n🚀 Début de l'enrôlement pour l'empreinte ID {finger_id}")
                    if enroll_fingerprint(finger_id):
                        # Mettre à jour l'étudiant avec l'ID d'empreinte dans la base de données
                        add_student_to_db(nom, prenom, uid, fingerprintid=finger_id)
                        print(f"🎉 Processus terminé avec succès pour {nom} {prenom}")
                    else:
                        print(f"❌ Échec de l'enrôlement de l'empreinte pour {nom} {prenom}")
                else:
                    print("❌ L'ID doit être compris entre 1 et 127.")
            except ValueError:
                print("❌ Veuillez entrer un ID valide.")
        else:
            print("❌ Erreur lors de la lecture de la carte RFID")
        
        time.sleep(1)

    except Exception as e:
        print(f"🚨 Erreur : {e}")
        print("Attente de la prochaine lecture...")
        time.sleep(1)
    
    # Demander si on veut continuer
    continuer = input("\nVoulez-vous ajouter un autre étudiant ? (o/n): ").lower()
    if continuer != 'o':
        break

print("👋 Programme terminé")
ser.close()