/**
* Play notes on a stepper motor via GPIO on Raspberry Pi (uses Gertboard test suite)
*/

#include <stdio.h>
#include <stdlib.h>
#include <sys/types.h>
#include <stdint.h>
#include <errno.h>
#include <unistd.h>

#include "gb_common.h"


// 4 pins for stepper, last is the enable pin:
uint gpioPins[5] = {2, 17, 3, 18, 4};
int maxPosition = 4000;
uint microsecondsOverhead = 76;

int currentPosition = 0;
int currentPin = 0;
int currentDirection = 1;

typedef struct {
    uint32_t pause;
    uint32_t frequency;
    uint32_t duration;
} Note;


void setupGpio() {
    uint i;
    for (i = 0; i < 5; i++) {
        INP_GPIO(gpioPins[i]);
        OUT_GPIO(gpioPins[i]);
    }
}

void motorOn() {
    // enable H-bridge:
    GPIO_SET0 = 1 << gpioPins[4];
}

void motorOff() {
    uint i;
    for (i = 0; i < 4; i++) {
        GPIO_CLR0 = 1 << gpioPins[i];
    }

    // disable H-bridge:
    GPIO_CLR0 = 1 << gpioPins[4];
}

void motorStep(uint delay) {
    GPIO_SET0 = 1 << gpioPins[currentPin];
    usleep(delay);
    GPIO_CLR0 = 1 << gpioPins[currentPin];

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
}

void playFile(const char *content, uint size) {
    Note *notes = (Note *) content;
    uint countNotes = size / sizeof(Note);
    int steps, delay;
    int n, s;

    printf("Playing %d notes\n", countNotes);

    for (n = 0; n < countNotes; n++) {
        Note *note = &notes[n];

        motorOff();

        if (note->pause > 0) {
            usleep(note->pause);
        }

        motorOn();

        steps = note->duration * note->frequency / 1000000;
        delay = 1000000 / note->frequency - microsecondsOverhead;

        if (currentDirection < 0 && currentPosition - steps < 0) {
            currentDirection = 1;
        } else if (currentDirection > 0 && currentPosition + steps > maxPosition) {
            currentDirection = -1;
        }

        for (s = 0; s < steps; s++) {
            motorStep(delay);
        }
    }

    motorOff();
}

char * loadFile(const char *filename, uint *size) {
    FILE *file;
    char *content;

    file = fopen(filename, "rb");
    if (file == NULL) {
        return NULL;
    }

    fseek(file, 0, SEEK_END);
    *size = ftell(file);
    fseek(file, 0, SEEK_SET);

    content = malloc(*size);
    if (content == NULL) {
        return NULL;
    }

    if (*size != fread(content, sizeof(char), *size, file)) {
        free(content);
        return NULL;
    }

    fclose(file);

    return content;
}

int main(int argc, char* argv[]) {
    char *content;
    uint size;

    if (argc != 2) {
        fprintf(stderr, "Usage: %s <binary-note-file>\n", argv[0]);
        return 1;
    }

    content = loadFile(argv[1], &size);
    if (content == NULL) {
        fprintf(stderr, "Error loading file \"%s\": %s", argv[1], strerror(errno));
        return 1;
    }

    setup_io();
    setupGpio();
    playFile(content, size);
    restore_io();

    free(content);

    return 0;
}
