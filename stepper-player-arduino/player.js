'use strict';

var StepperPlayer = require('./stepper-player.js').StepperPlayer;
var stepperPlayer = new StepperPlayer('/dev/ttyACM0');

stepperPlayer.on('connect', function () {
    console.log('Connected to StepperPlayer Arduino');

    console.log('Playing 10 Hz');
    stepperPlayer.play(10);

    setTimeout(function () {
        console.log('Playing off');
        stepperPlayer.off();

        setTimeout(function () {
            console.log('Playing 5 Hz');
            stepperPlayer.play(5);

            setTimeout(function () {
                console.log('Ping');
                stepperPlayer.ping();

                setTimeout(function () {
                    console.log('Reset');
                    stepperPlayer.reset();

                    setTimeout(function () {
                        console.log('Close');
                        stepperPlayer.close();
                    }, 2000);
                }, 1000);
            }, 1000);
        }, 1000);
    }, 1000);
});

stepperPlayer.on('error', function (err) {
    console.log(err.stack);
});
