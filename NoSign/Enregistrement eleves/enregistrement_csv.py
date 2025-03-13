import csv
import time
from smartcard.System import readers
from smartcard.util import toHexString

# Nom du fichier CSV
CSV_FILE = "liste_etudiants.csv"

# Vérifier si le fichier existe, sinon créer l'en-tête
try:
    with open(CSV_FILE, "x", newline="", encoding="utf-8-sig") as file:
        writer = csv.writer(file, delimiter=";")
        writer.writerow(["nom", "prénom", "UID"])
except FileExistsError:
    pass  # Le fichier existe déjà, on ne fait rien

# Connexion au lecteur RFID
r = readers()
if len(r) == 0:
    print("Aucun lecteur RFID trouvé.")
    exit()

reader = r[0]
connection = reader.createConnection()

print("Veuillez poser la carte étudiante sur le lecteur avant la saisie d'information.")

# Boucle infinie pour saisir plusieurs utilisateurs
while True:
    # Demander les informations à l'utilisateur
    nom = input("Entrez le nom : ")
    prenom = input("Entrez le prénom : ")

    try:
        connection.connect()
        GET_UID_CMD = [0xFF, 0xCA, 0x00, 0x00, 0x00]
        response, sw1, sw2 = connection.transmit(GET_UID_CMD)

        if sw1 == 0x90 and sw2 == 0x00:
            uid = toHexString(response).replace(" ", "")  # 🔥 Correction ici !
            print(f"📡 UID de la carte détecté : {uid}")

            # Enregistrer dans le fichier CSV
            with open(CSV_FILE, "a", newline="", encoding="utf-8-sig") as file:
                writer = csv.writer(file, delimiter=";")
                writer.writerow([nom, prenom, uid])

            print(f"✅ Les données suivantes ont été correctement enregistrées : {nom} {prenom} - UID: {uid}")
        
        time.sleep(1)
    
    except Exception as e:
        print("🚨 En attente d'une carte...")
        time.sleep(1)
