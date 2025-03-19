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

# Fonction pour ajouter un étudiant dans la base de données
def add_student_to_db(nom, prenom, uid, fingerprint_id=None):
    try:
        db = mysql.connector.connect(**DB_CONFIG)
        cursor = db.cursor()

        # Vérifier si l'UID existe déjà dans la table `etudiants`
        cursor.execute("SELECT uid FROM etudiants WHERE uid = %s", (uid,))
        result = cursor.fetchone()

        if result is None:
            print(f"🆕 Nouvel étudiant détecté : {nom} {prenom}, UID : {uid}. Ajout dans la table 'etudiants'...")
            cursor.execute("INSERT INTO etudiants (nom, prenom, uid, fingerprintid) VALUES (%s, %s, %s, %s)", 
                           (nom, prenom, uid, fingerprint_id))
            db.commit()

        cursor.close()
        db.close()
        print(f"✅ Données enregistrées : {nom} {prenom} - UID: {uid}, Fingerprint ID: {fingerprint_id}")
    
    except mysql.connector.Error as err:
        print(f"❌ Erreur de connexion à la base de données : {err}")

# Fonction pour enregistrer une empreinte digitale
def enroll_fingerprint(finger_id):
    print(f"Enregistrement de l'empreinte pour l'ID {finger_id}")
    ser.write(f"{finger_id}\n".encode())  # Envoi de l'ID au Arduino
    
    # Attente que l'Arduino termine l'enrôlement
    while True:
        response = ser.readline().decode().strip()
        if response == "Stored!":
            print(f"Empreinte ID {finger_id} enregistrée avec succès!")
            return True
        elif response == "Fingerprint did not match":
            print("Les empreintes ne correspondent pas. Veuillez réessayer.")
        else:
            print(f"Réponse du capteur: {response}")
        
        time.sleep(1)

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
                        add_student_to_db(nom, prenom, uid, fingerprint_id=finger_id)
                else:
                    print("L'ID doit être compris entre 1 et 127.")
            except ValueError:
                print("Veuillez entrer un ID valide.")
        
        time.sleep(1)

    except Exception as e:
        print("🚨 Carte retirée trop tôt. Attente de la prochaine lecture...")
        time.sleep(1)
