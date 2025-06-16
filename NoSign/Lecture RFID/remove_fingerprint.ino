#include <Adafruit_Fingerprint.h>
#include <SoftwareSerial.h>

// Connexions du capteur AS608
// VCC -> 3.3V ou 5V
// GND -> GND
// TX (capteur) -> Pin 2 (Arduino)
// RX (capteur) -> Pin 3 (Arduino)

SoftwareSerial mySerial(2, 3);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);

void setup() {
  Serial.begin(9600);
  while (!Serial);
  
  Serial.println("Suppression d'empreinte digitale AS608");
  
  // Initialisation du capteur
  finger.begin(57600);
  delay(5);
  
  if (finger.verifyPassword()) {
    Serial.println("Capteur d'empreinte trouvé !");
  } else {
    Serial.println("Capteur d'empreinte non trouvé :(");
    while (1) { delay(1); }
  }
  
  Serial.println("Prêt à supprimer une empreinte.");
  Serial.println("Tapez l'ID à supprimer (0-199) suivi d'Entrée :");
}

void loop() {
  if (Serial.available()) {
    int id = Serial.parseInt();
    
    if (id >= 0 && id <= 199) {
      deleteFingerprint(id);
    } else {
      Serial.println("ID invalide. Utilisez un nombre entre 0 et 199.");
    }
    
    // Vider le buffer série
    while (Serial.available()) {
      Serial.read();
    }
    
    Serial.println("Tapez un autre ID à supprimer ou redémarrez :");
  }
}

uint8_t deleteFingerprint(uint8_t id) {
  Serial.print("Suppression de l'ID #");
  Serial.println(id);
  
  uint8_t p = finger.deleteModel(id);
  
  switch (p) {
    case FINGERPRINT_OK:
      Serial.println("Empreinte supprimée avec succès !");
      break;
      
    case FINGERPRINT_PACKETRECIEVEERR:
      Serial.println("Erreur de communication");
      return p;
      
    case FINGERPRINT_BADLOCATION:
      Serial.println("Impossible de supprimer à cet emplacement");
      return p;
      
    case FINGERPRINT_FLASHERR:
      Serial.println("Erreur d'écriture en mémoire flash");
      return p;
      
    default:
      Serial.print("Erreur inconnue : 0x");
      Serial.println(p, HEX);
      return p;
  }
  
  return p;
}

// Fonction optionnelle pour supprimer toutes les empreintes
void deleteAllFingerprints() {
  Serial.println("Suppression de toutes les empreintes...");
  
  uint8_t p = finger.emptyDatabase();
  
  if (p == FINGERPRINT_OK) {
    Serial.println("Toutes les empreintes ont été supprimées !");
  } else {
    Serial.print("Erreur lors de la suppression : 0x");
    Serial.println(p, HEX);
  }
}

// Fonction pour afficher le nombre d'empreintes stockées
void getTemplateCount() {
  finger.getTemplateCount();
  Serial.print("Nombre d'empreintes stockées : ");
  Serial.println(finger.templateCount);
}