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

def add_scan(uid):
    try:
        db = mysql.connector.connect(**DB_CONFIG)
        cursor = db.cursor()

        # Vérifier si l'UID existe déjà dans la table `etudiants`
        cursor.execute("SELECT uid FROM etudiants WHERE uid = %s", (uid,))
        result = cursor.fetchone()

        if result is None:
            print(f"🆕 Nouvel UID détecté : {uid}. Ajout dans la table 'etudiants'...")
            cursor.execute("INSERT INTO etudiants (uid) VALUES (%s)", (uid,))
            db.commit()

        # Insérer l'UID dans la table `presences`
        now = datetime.now()
        date_scan = now.strftime("%Y-%m-%d")
        heure_scan = now.strftime("%H:%M:%S")

        cursor.execute(
            "INSERT INTO presences (uid, date_scan, heure_scan) VALUES (%s, %s, %s)",
            (uid, date_scan, heure_scan)
        )
        db.commit()
        print(f"✅ Scan enregistré pour {uid} à {heure_scan}")

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
            uid = toHexString(response).replace(" ", "")  # 🔥 Correction ici !
            print(f"📡 UID détecté : {uid}")
            add_scan(uid)
        
        time.sleep(1)

    except Exception as e:
        print("🚨 Carte retirée trop tôt. Attente de la prochaine lecture...")
        time.sleep(1)
