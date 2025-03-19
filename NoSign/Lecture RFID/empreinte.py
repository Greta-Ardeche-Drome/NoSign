


#PERMET D'ENREGISTRER UNE EMPREINTE DANS LE LECTEUR


import serial
import time

# Configuration du port série
arduino_port = 'COM10'  # Remplace par le port série auquel ton Arduino est connecté
baud_rate = 9600      # Vitesse de communication, doit correspondre à celle de ton Arduino

# Initialisation de la connexion série
ser = serial.Serial(arduino_port, baud_rate, timeout=1)
time.sleep(2)  # Attente pour que la connexion série soit prête

def enroll_fingerprint(finger_id):
    # Envoi de l'ID pour l'enrôlement de l'empreinte
    print(f"Enregistrement de l'empreinte pour l'ID {finger_id}")
    ser.write(f"{finger_id}\n".encode())  # Envoi de l'ID au Arduino
    
    # Attente que l'Arduino termine l'enrôlement
    while True:
        response = ser.readline().decode().strip()
        if response == "Stored!":
            print(f"Empreinte ID {finger_id} enregistrée avec succès!")
            break
        elif response == "Fingerprint did not match":
            print("Les empreintes ne correspondent pas. Veuillez réessayer.")
        else:
            print(f"Réponse du capteur: {response}")
        
        time.sleep(1)

def main():
    # Demande à l'utilisateur d'entrer un ID pour l'enregistrement de l'empreinte
    try:
        finger_id = int(input("Entrez l'ID (1-127) de l'empreinte à enregistrer: "))
        if 1 <= finger_id <= 127:
            enroll_fingerprint(finger_id)
        else:
            print("L'ID doit être compris entre 1 et 127.")
    except ValueError:
        print("Veuillez entrer un ID valide.")
    finally:
        ser.close()  # Fermeture de la connexion série

if __name__ == "__main__":
    main()
