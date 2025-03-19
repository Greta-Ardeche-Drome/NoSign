

#DOIT CHOISIR LE CHOIX 1 DE L'ARDUINO


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
    'charset': 'utf8mb4',  # Utilisation de utf8mb4
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
arduino_port = 'COM10'  # Remplace par le port série auquel ton Arduino est connecté
baud_rate = 9600        # Vitesse de communication, doit correspondre à celle de ton Arduino

# Initialisation de la connexion série pour l'empreinte digitale
ser = serial.Serial(arduino_port, baud_rate, timeout=1)
time.sleep(2)  # Attente pour que la connexion série soit prête

# Fonction pour ajouter ou mettre à jour un étudiant dans la base de données
def add_student_to_db(nom, prenom, uid, fingerprintid=None):
    try:
        db = mysql.connector.connect(**DB_CONFIG)
        cursor = db.cursor()

        # Vérifier si l'UID existe déjà dans la table `etudiants`
        cursor.execute("SELECT id FROM etudiants WHERE uid = %s", (uid,))
        result = cursor.fetchone()

        if result is None:
            # L'UID n'existe pas encore, ajouter un nouvel étudiant
            print(f"🆕 Nouvel étudiant détecté : {nom} {prenom}, UID : {uid}. Ajout dans la table 'etudiants'...")
            cursor.execute("INSERT INTO etudiants (nom, prenom, uid, fingerprintid) VALUES (%s, %s, %s, %s)", 
                           (nom, prenom, uid, fingerprintid))
            db.commit()
            student_id = cursor.lastrowid  # Récupérer l'ID de l'étudiant après insertion
            print(f"✅ Étudiant ajouté avec succès. ID de l'étudiant : {student_id}")
        else:
            # L'UID existe déjà, mettre à jour l'empreinte
            print(f"✅ Étudiant déjà présent, mise à jour de l'empreinte pour l'UID : {uid}")
            cursor.execute("UPDATE etudiants SET fingerprintid = %s WHERE uid = %s", (fingerprintid, uid))
            db.commit()
            print(f"✅ Empreinte mise à jour avec succès pour l'UID {uid}.")

        cursor.close()
        db.close()

    except mysql.connector.Error as err:
        print(f"❌ Erreur de connexion à la base de données : {err}")

# Fonction pour enregistrer une empreinte digitale
def enroll_fingerprint(finger_id):
    print(f"📌 Début de l'enrôlement de l'empreinte pour l'ID {finger_id}")

    # Forcer l'envoi de "1" pour entrer en mode enregistrement
    ser.write("1\n".encode())
    time.sleep(1)

    # Vérifier si le capteur demande l'ID
    while True:
        response = ser.readline().decode().strip()
        print(f"🔄 Réponse du capteur: {response}")

        if "Enter the ID" in response:
            ser.write(f"{finger_id}\n".encode())  # Envoi de l'ID
            print(f"✅ ID de l'empreinte envoyé: {finger_id}")
            break
        time.sleep(1)

    # Vérifier si l'ID est bien pris en compte
    while True:
        response = ser.readline().decode().strip()
        print(f"🔄 Réponse du capteur: {response}")

        if f"Enrolling ID #{finger_id}" in response:
            print(f"✅ Confirmation : L'ID {finger_id} est bien pris en compte.")
            break
        elif "Enrolling ID #1" in response:
            print("⚠️ ERREUR : Le capteur enregistre sous ID #1 au lieu de l'ID demandé !")
            return False  # Stop le script pour éviter un mauvais enrôlement
        
        time.sleep(1)

    # Attendre l'enregistrement de l'empreinte
    while True:
        response = ser.readline().decode().strip()
        print(f"🔄 Réponse du capteur: {response}")

        if "Stored!" in response:
            print(f"✅ Empreinte ID {finger_id} enregistrée avec succès !")
            return True
        elif "Fingerprint did not match" in response:
            print("⚠️ Les empreintes ne correspondent pas. Veuillez réessayer.")
        elif "Please choose an option:" in response:
            print("ℹ️ Capteur prêt pour l'enrôlement ou la reconnaissance.")
        
        time.sleep(1)

    return False


# Boucle pour saisir plusieurs utilisateurs
while True:
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
            
            # Enregistrer les informations de l'étudiant dans la base de données
            add_student_to_db(nom, prenom, uid)
            
            # Demander l'enregistrement de l'empreinte digitale
            try:
                finger_id = int(input("Entrez l'ID (1-127) de l'empreinte à enregistrer: "))
                if 1 <= finger_id <= 127:
                    if enroll_fingerprint(finger_id):
                        # Mettre à jour l'étudiant avec l'ID d'empreinte dans la base de données
                        add_student_to_db(nom, prenom, uid, fingerprintid=finger_id)
                else:
                    print("L'ID doit être compris entre 1 et 127.")
            except ValueError:
                print("Veuillez entrer un ID valide.")
        
        time.sleep(1)

    except Exception as e:
        print("🚨 Carte retirée trop tôt. Attente de la prochaine lecture...")
        time.sleep(1)
