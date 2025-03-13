import csv
import time
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

# Fonction pour ajouter un étudiant dans la base de données
def add_student_to_db(nom, prenom, uid):
    try:
        db = mysql.connector.connect(**DB_CONFIG)
        cursor = db.cursor()

        # Vérifier si l'UID existe déjà dans la table `etudiants`
        cursor.execute("SELECT uid FROM etudiants WHERE uid = %s", (uid,))
        result = cursor.fetchone()

        if result is None:
            print(f"🆕 Nouvel étudiant détecté : {nom} {prenom}, UID : {uid}. Ajout dans la table 'etudiants'...")
            cursor.execute("INSERT INTO etudiants (nom, prenom, uid) VALUES (%s, %s, %s)", (nom, prenom, uid))
            db.commit()

        cursor.close()
        db.close()
        print(f"✅ Données enregistrées : {nom} {prenom} - UID: {uid}")
    
    except mysql.connector.Error as err:
        print(f"❌ Erreur de connexion à la base de données : {err}")

# Boucle infinie pour saisir plusieurs utilisateurs
while True:
    # Demander les informations à l'utilisateur
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
            add_student_to_db(nom, prenom, uid)
        
        time.sleep(1)

    except Exception as e:
        print("🚨 Carte retirée trop tôt. Attente de la prochaine lecture...")
        time.sleep(1)
