#include <Adafruit_Fingerprint.h>

#if (defined(__AVR__) || defined(ESP8266)) && !defined(__AVR_ATmega2560__)
SoftwareSerial mySerial(2, 3);  // Utilisation de la communication série logicielle
#else
#define mySerial Serial1  // Utilisation de la communication série matérielle
#endif

Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);

uint8_t id;

void setup()
{
  Serial.begin(9600);
  while (!Serial);  // Pour les boards comme Yun/Leo/Micro/Zero/...
  delay(100);
  Serial.println("\n\nAdafruit Fingerprint Sensor");
  
  finger.begin(57600);  // Initialiser le capteur à une vitesse de 57600 bauds
  delay(5);
  
  if (finger.verifyPassword()) {
    Serial.println("Fingerprint sensor found!");
  } else {
    Serial.println("Could not find fingerprint sensor :(");
    while (1) { delay(1); }
  }

  Serial.println(F("Reading sensor parameters"));
  finger.getParameters();
  Serial.print(F("Sensor capacity: ")); Serial.println(finger.capacity);
}

uint8_t readNumber(void) {
  uint8_t num = 0;
  while (num == 0) {
    while (!Serial.available());
    num = Serial.parseInt();
  }
  return num;
}

void loop()
{
  Serial.println("Ready to enroll or recognize a fingerprint!");
  Serial.println("Please choose an option: 1 for Enroll, 2 for Recognize");
  
  uint8_t choice = readNumber();
  
  if (choice == 1) {
    Serial.println("Enter the ID # (from 1 to 127) to enroll this fingerprint...");
    id = readNumber();
    if (id == 0) return;  // ID #0 is not allowed, try again!
    Serial.print("Enrolling ID #");
    Serial.println(id);

    while (!getFingerprintEnroll());
  } else if (choice == 2) {
    getFingerprintID();
  }
}

uint8_t getFingerprintEnroll() {
  int p = -1;
  Serial.print("Waiting for a valid finger to enroll as #"); Serial.println(id);
  
  while (p != FINGERPRINT_OK) {
    p = finger.getImage();
    switch (p) {
      case FINGERPRINT_OK:
        Serial.println("Image taken");
        break;
      case FINGERPRINT_NOFINGER:
        Serial.print(".");
        break;
      case FINGERPRINT_PACKETRECIEVEERR:
        Serial.println("Communication error");
        break;
      case FINGERPRINT_IMAGEFAIL:
        Serial.println("Imaging error");
        break;
      default:
        Serial.println("Unknown error");
        break;
    }
  }

  p = finger.image2Tz(1);
  if (p != FINGERPRINT_OK) return p;

  Serial.println("Remove finger");
  delay(2000);
  
  p = 0;
  while (p != FINGERPRINT_NOFINGER) {
    p = finger.getImage();
  }
  
  Serial.println("Place same finger again");
  
  while (p != FINGERPRINT_OK) {
    p = finger.getImage();
    if (p == FINGERPRINT_OK) {
      Serial.println("Image taken");
    }
  }

  p = finger.image2Tz(2);
  if (p != FINGERPRINT_OK) return p;

  p = finger.createModel();
  if (p != FINGERPRINT_OK) return p;

  Serial.print("ID "); Serial.println(id);
  p = finger.storeModel(id);
  if (p == FINGERPRINT_OK) {
    Serial.println("Fingerprint stored!");
  } else {
    Serial.println("Error storing fingerprint");
  }

  return true;
}

uint8_t getFingerprintID() {
  uint8_t p = finger.getImage();
  if (p != FINGERPRINT_OK) return p;

  p = finger.image2Tz();
  if (p != FINGERPRINT_OK) return p;

  p = finger.fingerSearch();
  if (p == FINGERPRINT_OK) {
    Serial.println("Found a match!");
  } else if (p == FINGERPRINT_NOTFOUND) {
    Serial.println("Did not find a match");
  } else {
    Serial.println("Error");
  }

  if (p == FINGERPRINT_OK) {
    Serial.print("Found ID #"); Serial.print(finger.fingerID);
    Serial.print(" with confidence of "); Serial.println(finger.confidence);
  }

  return finger.fingerID;
}
