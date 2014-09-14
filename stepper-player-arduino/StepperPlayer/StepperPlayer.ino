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
uint8_t stepperPins[] = {8, 9, 10, 11};
uint8_t stepperEnablePin = 12;
//TODO: unsigned long minPowerTime = 750; // time in µs needed to move one step - power off after this time for energy-saving/heat-sink ("chopper")
//TODO: unsigned int chopperResolution = 30; // lowest playable note is ~24270 µs/step (~41 Hz) -> div/30 needed to get near minEnableTime

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
    stepperOff();

    // frequency generator:
    Timer1.initialize();
    Timer1.stop();

    // serial:
    serialInputLine.reserve(maxLineLength + 1);
    serialInputLine = "";

    Serial.begin(115200);
    Serial.println("ok:Welcome to StepperPlayer - Valid commands: play:<frequency>, off, reset[:<frequency>], ping");

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
                Serial.println("error:Line too long");
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
        Serial.println("error:Empty command");
        return;
    }

    if (command == "play") {
        if (frequency < 0.1 || frequency > 20000) {
            Serial.print("error:Invalid frequency ");
            Serial.print(frequency);
            Serial.println(" - valid only >= 0.1 && <= 20000");
            return;
        }

        long period = (long) (1000000.0 / frequency); // time between steps in microseconds

        Timer1.start(); // start next period at zero
        Timer1.attachInterrupt(moveStepper, period);

        Serial.print("ok:Playing frequency ");
        Serial.print(frequency);
        Serial.print(" (period = ");
        Serial.print(period);
        Serial.println(")");

        lcd.clear();
        lcd.print("Playing Hz:");
        lcd.setCursor(0, 1);
        lcd.print(frequency);
    } else if (command == "off") {
        Timer1.detachInterrupt();
        Timer1.stop();
        stepperOff();

        Serial.println("ok:Playing off");

        lcd.clear();
        lcd.print("Pause");
    } else if (command == "reset") {
        Timer1.detachInterrupt();
        Timer1.stop();
        stepperOff();

        lcd.clear();
        lcd.print("Resetting");

        // TODO: Implement stepper logic here
        delay(1000);

        Serial.println("ok:Reset complete");

        lcd.clear();
        lcd.print("Resetted");
    } else if (command == "ping") {
        Serial.println("ok:Pong!");
    } else {
        Serial.print("error:Invalid command \"");
        Serial.print(command);
        Serial.println("\"");
    }
}

/**
 * Called from timer interrupt
 */
void moveStepper() {
    // TODO: Implement stepper logic here
}

void stepperOff() {
    digitalWrite(stepperPins[0], LOW);
    digitalWrite(stepperPins[2], LOW);
    digitalWrite(stepperPins[3], LOW);
    digitalWrite(stepperPins[4], LOW);
    digitalWrite(stepperEnablePin, LOW);
}

