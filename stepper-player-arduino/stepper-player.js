'use strict';

var serialport = require('serialport');
var SerialPort = serialport.SerialPort;
var EventEmitter = require('events').EventEmitter;
var util = require('util');

var StepperPlayer = function(path) {
    var self = this;

    self.serialPort = new SerialPort(path, {
        baudrate: 115200,
        parser: serialport.parsers.readline('\n')
    });

    self.serialPort.on('open', function onOpen() {
        var initialized = false;

        self.serialPort.on('data', function onData(data) {
            if (!initialized) {
                initialized = true;
                self.emit('connect');
            }

            console.log('TODO: Parse response: ' + data);
        });
    });
};

util.inherits(StepperPlayer, EventEmitter);

StepperPlayer.prototype.play = function play(frequency) {
    this.serialPort.write('play:' + frequency + '\n');
}

StepperPlayer.prototype.off = function off() {
    this.serialPort.write('off\n');
}

StepperPlayer.prototype.reset = function reset(frequency) {
    this.serialPort.write('reset' + (frequency ? ':' + frequency : '') + '\n');
}

StepperPlayer.prototype.ping = function ping() {
    this.serialPort.write('ping\n');
}

StepperPlayer.prototype.close = function close() {
    this.serialPort.close();
}

module.exports = {
    StepperPlayer: StepperPlayer
};
