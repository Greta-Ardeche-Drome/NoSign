# FONCTIONNE AVEC LE CHOIX 1 

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

# Connexion série avec l'Arduino
arduino_port = 'COM10'  # Remplace par le port correct
baud_rate = 9600
ser = serial.Serial(arduino_port, baud_rate, timeout=1)
time.sleep(2)

# Fonction pour ajouter un étudiant dans la BDD
def add_student_to_db(nom, prenom, uid, fingerprintid=None):
    try:
        db = mysql.connector.connect(**DB_CONFIG)
        cursor = db.cursor()

        # Vérifier si l'étudiant existe déjà
        cursor.execute("SELECT id FROM etudiants WHERE uid = %s", (uid,))
        result = cursor.fetchone()

        if result is None:
            # Ajouter un nouvel étudiant
            cursor.execute("INSERT INTO etudiants (nom, prenom, uid, fingerprintid) VALUES (%s, %s, %s, %s)", 
                           (nom, prenom, uid, fingerprintid))
            db.commit()
            student_id = cursor.lastrowid
            print(f"✅ Étudiant ajouté avec succès. ID : {student_id}")
        else:
            # Mettre à jour l'empreinte
            cursor.execute("UPDATE etudiants SET fingerprintid = %s WHERE uid = %s", (fingerprintid, uid))
            db.commit()
            print(f"✅ Empreinte mise à jour pour l'UID {uid}.")

        cursor.close()
        db.close()
    except mysql.connector.Error as err:
        print(f"❌ Erreur MySQL : {err}")

# Fonction pour enrôler l'empreinte
def enroll_fingerprint(finger_id):
    print(f"📌 Enrôlement de l'empreinte pour l'ID {finger_id}")
    ser.write("1\n".encode())  # Mode enrôlement
    time.sleep(1)

    # Attente de la demande d'ID
    while True:
        response = ser.readline().decode().strip()
        print(f"🔄 Réponse : {response}")

        if "Enter the ID" in response:
            ser.write(f"{finger_id}\n".encode())  # Envoi ID
            print(f"✅ ID {finger_id} envoyé.")
            break
        time.sleep(1)

    # Vérification de l'enregistrement
    while True:
        response = ser.readline().decode().strip()
        print(f"🔄 Réponse : {response}")

        if "Fingerprint stored!" in response:
            print(f"✅ Empreinte ID {finger_id} enregistrée avec succès !")
            return True  # Retourner True pour confirmer la réussite

        elif "Fingerprint did not match" in response:
            print("⚠️ Empreintes non identiques.")
            return False
        elif "Please choose an option:" in response:
            print("ℹ️ Capteur prêt pour un nouvel enrôlement.")
            return False
        time.sleep(1)

    return False

# Boucle principale
while True:
    print("\nPosez la carte sur le lecteur RFID.")
    nom = input("Entrez le nom : ")
    prenom = input("Entrez le prénom : ")

    try:
        connection.connect()
        GET_UID_CMD = [0xFF, 0xCA, 0x00, 0x00, 0x00]
        response, sw1, sw2 = connection.transmit(GET_UID_CMD)

        if sw1 == 0x90 and sw2 == 0x00:
            uid = toHexString(response).replace(" ", "")
            print(f"📡 UID détecté : {uid}")

            # Ajouter l'étudiant sans empreinte
            add_student_to_db(nom, prenom, uid)

            # Demander l'ID d'empreinte
            try:
                finger_id = int(input("Entrez l'ID de l'empreinte (1-127) : "))
                if 1 <= finger_id <= 127:
                    if enroll_fingerprint(finger_id):
                        # Mise à jour avec fingerprintid
                        add_student_to_db(nom, prenom, uid, fingerprintid=finger_id)
                    else:
                        print("⚠️ Échec de l'enregistrement de l'empreinte.")
                else:
                    print("⚠️ L'ID doit être entre 1 et 127.")
            except ValueError:
                print("⚠️ Entrez un nombre valide.")

        time.sleep(1)

    except Exception as e:
        print("🚨 Erreur de lecture de la carte.")
        time.sleep(1)
