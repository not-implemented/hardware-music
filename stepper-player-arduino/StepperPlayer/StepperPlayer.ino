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
long minPowerTime = 750; // time in µs needed to move one step - power off after this time for energy-saving/heat-sink ("chopper")
long periodDenominator; // current period is divided by this to handle minPowerTime
long powerNumerator; // count of short periods while power is on
long currentNumerator; // current count of short periods in one full period

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

        if (period < minPowerTime) {
            Serial.print("error:Frequency ");
            Serial.print(frequency);
            Serial.print(" too high - max frequency is currently ");
            Serial.print(1000000.0 / minPowerTime);
            Serial.print(" (based on minPowerTime)\n");
            return;
        }

        long newPeriodDenominator, newPowerNumerator;
        long shortPeriod = calcShortPeriod(period, &newPeriodDenominator, &newPowerNumerator);

        stepperOff();

        periodDenominator = newPeriodDenominator;
        powerNumerator = newPowerNumerator;
        currentNumerator = 0;

        Timer1.start(); // start next period at zero
        Timer1.attachInterrupt(timerInterrupt, shortPeriod);

        Serial.print("ok:Playing frequency ");
        Serial.print(frequency);
        Serial.print(" (period = ");
        Serial.print(period);
        Serial.print("µs (-");
        Serial.print(period - (shortPeriod * periodDenominator));
        Serial.print("); shortPeriod = ");
        Serial.print(shortPeriod);
        Serial.print("µs; powerFraction = ");
        Serial.print(powerNumerator);
        Serial.print("/");
        Serial.print(periodDenominator);
        Serial.print(")\n");

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
 * Calculate shortPeriod, periodDenominator and powerNumerator to handle minPowerTime
 */
long calcShortPeriod(long period, long *newPeriodDenominator, long *newPowerNumerator) {
    long denominator, minDenominator, maxDenominator, bestDenominator = 1;
    long difference, minDifference = period;
    long shortPeriod, powerPeriods;

    minDenominator = max(period / (minPowerTime + 100), 1);
    maxDenominator = max(period / (minPowerTime / 4 - 20), 1);

    if (maxDenominator - minDenominator > 500) {
        // reduce search-CPU time for really low frequencies:
        minDenominator = max(period / (minPowerTime + 20), 1);
        maxDenominator = max(period / (minPowerTime - 20), 1);
    }

    for (denominator = minDenominator; denominator <= maxDenominator; denominator++) {
        shortPeriod = period / denominator;
        powerPeriods = ceil((float) minPowerTime / shortPeriod);

        difference = (period - shortPeriod * denominator) * 20 + // higher weight of full period difference
            (shortPeriod * powerPeriods - minPowerTime); // normal weight of powerTime difference

        if (difference < minDifference) {
            bestDenominator = denominator;
            minDifference = difference;
        }

        if (minDifference < 100) {
            break; // prefer lower denominators if difference is acceptable
        }
    }

    shortPeriod = period / bestDenominator;
    *newPeriodDenominator = bestDenominator;
    *newPowerNumerator = ceil((float) minPowerTime / shortPeriod);

    return shortPeriod;
}

/**
 * Called on every short period (from hardware-timer-interrupt)
 */
void timerInterrupt() {
    currentNumerator++;

    if (currentNumerator >= powerNumerator) {
        digitalWrite(stepperEnablePin, LOW);
    }

    if (currentNumerator >= periodDenominator) {
        currentNumerator = 0;
        moveStepper();
    }
}

/**
 * Called on every full period
 */
void moveStepper() {
    // TODO: Implement stepper logic here
    digitalWrite(stepperEnablePin, HIGH);
}

void stepperOff() {
    Timer1.detachInterrupt();
    Timer1.stop();

    digitalWrite(stepperEnablePin, LOW);
    digitalWrite(stepperPins[0], LOW);
    digitalWrite(stepperPins[2], LOW);
    digitalWrite(stepperPins[3], LOW);
    digitalWrite(stepperPins[4], LOW);
}
