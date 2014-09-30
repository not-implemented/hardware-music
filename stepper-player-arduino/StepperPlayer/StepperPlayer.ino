/**
 * StepperPlayer: Playing given frequency from serial input on a stepper motor
 *
 * Line-based protocol ('\n') - one command per line:
 * - "play:<frequency>": Frequency as ASCII-float (i.e. "play:430.538") to play until "off" command is sent
 * - "off": Stop playing
 * - "reset" or "reset:<frequency>": Move stepper back to start position at which Arduino was switched on
 *    - Default frequency while resetting is 440 Hz
 *    - Command is blocking - response is sent after resetting is complete - no other commands are processed
 *      while resetting
 * - "ping": Just returns a pong-message
 *
 * Response:
 * - "ok:<message>": Initial greeting is sent. All commands are confirmed with an info message
 * - "error:<message>": Invalid commands are confirmed with an error message
 */

#include "TimerOne.h"
#include <LiquidCrystal.h>

// stepper:
uint8_t stepperPins[] = {8, 10, 9, 11};
uint8_t stepperEnablePin = 12;
long maxPosition = 4000;
long currentPosition = 0;
int currentPin = 3;
int currentDirection = 1;

// serial:
const unsigned int maxLineLength = 127;
String serialInputLine;
boolean serialInputOverflow = false;

// LED:
uint8_t ledPin = LED_BUILTIN;

// LCD:
LiquidCrystal lcd(7, 6, 5, 4, 3, 2);

void setup() {
    // stepper:
    pinMode(stepperPins[0], OUTPUT);
    pinMode(stepperPins[1], OUTPUT);
    pinMode(stepperPins[2], OUTPUT);
    pinMode(stepperPins[3], OUTPUT);
    pinMode(stepperEnablePin, OUTPUT);
    Timer1.initialize();
    stepperOff();

    // serial:
    serialInputLine.reserve(maxLineLength + 1);
    serialInputLine = "";

    Serial.begin(115200);
    Serial.print("ok:Welcome to StepperPlayer - Valid commands: play:<frequency>, off, reset[:<frequency>], ping\n");

    // LED:
    pinMode(ledPin, OUTPUT);
    digitalWrite(ledPin, LOW);

    // LCD:
    lcd.begin(16, 2);
    lcd.print("Welcome to");
    lcd.setCursor(0, 1);
    lcd.print("StepperPlayer");
}

void loop() {
    // Nothing to do here ... all events are handled by serialEvent() and timer-interrupt
}

/**
 * Called between loop() calls, when serial data is available
 */
void serialEvent() {
    while (Serial.available()) {
        char receivedByte = (char) Serial.read();
    
        if (receivedByte == '\n') {
            if (serialInputOverflow) {
                Serial.print("error:Line too long\n");
            } else {
                processLine();
            }

            serialInputLine = "";
            serialInputOverflow = false;
        } else {
            if (serialInputLine.length() >= maxLineLength) {
                serialInputOverflow = true;
            } else {
                serialInputLine += receivedByte;
            }
        }
    }
}

void processLine() {
    String command;
    float frequency = 0;

    int pos = serialInputLine.indexOf(':');
    if (pos != -1) {
        command = serialInputLine.substring(0, pos);

        char valueString[serialInputLine.length() - (pos + 1) + 1];
        serialInputLine.toCharArray(valueString, sizeof(valueString), pos + 1);
        frequency = atof(valueString);
    } else {
        command = serialInputLine;
    }

    if (command.length() == 0) {
        Serial.print("error:Empty command\n");
        return;
    }

    if (command == "play") {
        if (frequency < 0.1 || frequency > 16000) {
            Serial.print("error:Invalid frequency ");
            Serial.print(frequency);
            Serial.print(" - frequency < 0.1 || > 16000 not allowed\n");
            return;
        }

        long period = (long) (1000000.0 / frequency); // time between steps in microseconds

        stepperOff();
        moveStepper();

        Timer1.start(); // start next period at zero
        Timer1.attachInterrupt(moveStepper, period);

        Serial.print("ok:Playing frequency ");
        Serial.print(frequency);
        Serial.print(" (period = ");
        Serial.print(period);
        Serial.print("µs)\n");

        lcd.clear();
        lcd.print("Playing:");
        lcd.setCursor(0, 1);
        lcd.print(frequency);
        lcd.print(" Hz");
    } else if (command == "off") {
        stepperOff();

        Serial.print("ok:Playing off\n");

        lcd.clear();
        lcd.print("Pause");
    } else if (command == "reset") {
        stepperOff();

        lcd.clear();
        lcd.print("Resetting to");
        lcd.setCursor(0, 1);
        lcd.print("home position");

        // TODO: Implement stepper logic here
        delay(1000);

        Serial.print("ok:Resetted to home position\n");

        lcd.clear();
        lcd.print("Resetted");
    } else if (command == "ping") {
        Serial.print("ok:Pong!\n");
    } else {
        Serial.print("error:Invalid command \"");
        Serial.print(command);
        Serial.print("\"\n");
    }
}

/**
 * Called on every period (from hardware-timer-interrupt)
 */
void moveStepper() {
    digitalWrite(stepperPins[currentPin], LOW);

    if (currentDirection < 0 && currentPosition <= 0) {
        currentDirection = 1;
    } else if (currentDirection > 0 && currentPosition >= maxPosition) {
        currentDirection = -1;
    }

    currentPosition += currentDirection;

    if (currentDirection < 0 && currentPin <= 0) {
        currentPin = 3;
    } else if (currentDirection > 0 && currentPin >= 3) {
        currentPin = 0;
    } else {
        currentPin += currentDirection;
    }

    digitalWrite(stepperPins[currentPin], HIGH);
    digitalWrite(stepperEnablePin, HIGH);
}

void stepperOff() {
    Timer1.detachInterrupt();
    Timer1.stop();

    digitalWrite(stepperEnablePin, LOW);
    digitalWrite(stepperPins[0], LOW);
    digitalWrite(stepperPins[1], LOW);
    digitalWrite(stepperPins[2], LOW);
    digitalWrite(stepperPins[3], LOW);
}
