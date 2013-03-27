/**
* Play notes on a stepper motor via GPIO on Raspberry Pi (uses Gertboard test suite)
*/

#include <string.h>
#include <sys/time.h>
#include <stdio.h>

#include "gb_common.h"

void setup_gpio(void) {
  INP_GPIO(2); OUT_GPIO(2);
  INP_GPIO(3); OUT_GPIO(3);
  INP_GPIO(17); OUT_GPIO(17);
  INP_GPIO(18); OUT_GPIO(18);
}

void motorStep(unsigned int delay, unsigned int direction, unsigned int halfstep) {
    unsigned int forward[] = {2, 17, 3, 18};
    unsigned int backward[] = {18, 3, 17, 2};
    unsigned int *order = direction ? &backward : &forward;

    if (halfstep) {
        GPIO_SET0 = 1<<order[0];
        usleep(delay);
        GPIO_CLR0 = 1<<order[3];
        usleep(delay);

        GPIO_SET0 = 1<<order[1];
        usleep(delay);
        GPIO_CLR0 = 1<<order[0];
        usleep(delay);

        GPIO_SET0 = 1<<order[2];
        usleep(delay);
        GPIO_CLR0 = 1<<order[1];
        usleep(delay);

        GPIO_SET0 = 1<<order[3];
        usleep(delay);
        GPIO_CLR0 = 1<<order[2];
        usleep(delay);
    } else {
        GPIO_SET0 = 1<<order[0];
        usleep(delay);
        GPIO_CLR0 = 1<<order[0];

        GPIO_SET0 = 1<<order[1];
        usleep(delay);
        GPIO_CLR0 = 1<<order[1];

        GPIO_SET0 = 1<<order[2];
        usleep(delay);
        GPIO_CLR0 = 1<<order[2];

        GPIO_SET0 = 1<<order[3];
        usleep(delay);
        GPIO_CLR0 = 1<<order[3];
    }
}

int main(int argc, char* argv[]) {
    int i, j, direction;

    setup_io();

    setup_gpio();

    unsigned int song[][4] = {
        {0, 192, 3329, 1},
        {0, 85, 2475, 1},
        {0, 304, 2069, 1},
        {0, 85, 2475, 1},
        {0, 241, 2627, 1},
        {0, 43, 2475, 1},
        {0, 48, 2197, 1},
        {0, 341, 2475, 1},
    };

    unsigned int count = 8;

    for (i = 0; i < count; i++) {
        unsigned int *note = &song[i];
        unsigned int pause = note[0];

        GPIO_CLR0 = 1<<2;
        GPIO_CLR0 = 1<<3;
        GPIO_CLR0 = 1<<17;
        GPIO_CLR0 = 1<<18;

        if (pause > 0) {
            usleep(pause);
        }

        direction = note[3];

        for (j = 0; j < note[1] / 4; j++) {
            motorStep(note[2], direction, 0);
        }
    }

    GPIO_CLR0 = 1<<2;
    GPIO_CLR0 = 1<<3;
    GPIO_CLR0 = 1<<17;
    GPIO_CLR0 = 1<<18;

    restore_io();
}
