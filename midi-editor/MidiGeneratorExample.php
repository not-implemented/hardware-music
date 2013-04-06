<?php

require_once 'MidiFile.php';
require_once 'MidiGenerator.php';

$midiGeneratorExample = new MidiGeneratorExample();
$midiGeneratorExample->main();

class MidiGeneratorExample {
    public function main() {
        $midiGenerator = new MidiGenerator();
        $midiGenerator->program = 40;

        // Sieben Tage lang:
        $notes = array(
            'D5:1/8', 'D5:1/8', 'H4:1/8', 'C5:1/4', 'A4:1/4',
            'A4:1/8', 'D5:1/8', 'D5:1/8', 'C5:1/8', 'H4:1/8',
            'D5:1/8', 'D5:1/16', 'C5:1/16', 'H4:1/8', 'C5:1/4', 'A4:1/4',
            'H4:1/8', 'G4:1/8', 'A4:3/4',

            'D5:1/8', 'D5:1/16', 'C5:1/16', 'H4:1/8', 'C5:1/4', 'A4:1/4',
            'A4:1/8', 'D5:1/8', 'D5:1/8', 'C5:1/8', 'H4:1/8',
            'D5:1/8', 'D5:1/16', 'C5:1/16', 'H4:1/8', 'C5:1/4', 'A4:1/4',
            'H4:1/8', 'G4:1/8', 'A4:3/4',

            'A4:1/8', 'C5:1/8', 'D5:1/8', 'E5:1/4', 'E5:1/4', 'F5:1/8', 'D5:1/8', 'E5:3/4',

            'D5:1/8', 'D5:1/16', 'C5:1/16', 'H4:1/8', 'C5:1/4', 'A4:1/4',
            'A4:1/8', 'D5:1/8', 'D5:1/8', 'C5:1/8', 'H4:1/8',
            'D5:1/8', 'D5:1/16', 'C5:1/16', 'H4:1/8', 'C5:1/4', 'A4:1/4',
            'H4:1/8', 'G4:1/8', 'A4:3/4',

            'A4:1/8', 'C5:1/8', 'D5:1/8', 'E5:1/4', 'E5:1/4', 'F5:1/8', 'D5:1/8', 'E5:3/4',

            'D5:1/8', 'D5:1/16', 'C5:1/16', 'H4:1/8', 'C5:1/4', 'A4:1/4',
            'A4:1/8', 'D5:1/8', 'D5:1/8', 'C5:1/8', 'H4:1/8',
            'D5:1/8', 'D5:1/16', 'C5:1/16', 'H4:1/8', 'C5:1/4', 'A4:1/4',
            'H4:1/8', 'G4:1/8', 'A4:3/4',
        );

        $midiFile = $midiGenerator->convertNotesToMidi($notes);
        $midiFile->save('Sieben Tage lang.mid');
        foreach ($midiFile->logMessages as $logMessage) {
            echo $logMessage . PHP_EOL;
        }


        // Tonleiter:
        $notes = array(
            'E1:1/4', 'F1:1/4', 'G1:1/4', 'A1:1/4', 'H1:1/4',
            'C2:1/4', 'D2:1/4', 'E2:1/4', 'F2:1/4', 'G2:1/4', 'A2:1/4', 'H2:1/4',
            'C3:1/4', 'D3:1/4', 'E3:1/4', 'F3:1/4', 'G3:1/4', 'A3:1/4', 'H3:1/4',
            'C4:1/4', 'D4:1/4', 'E4:1/4', 'F4:1/4', 'G4:1/4', 'A4:1/4', 'H4:1/4',
            'C5:1/4', 'D5:1/4', 'E5:1/4', 'F5:1/4', 'G5:1/4', 'A5:1/4', 'H5:1/4',
            'C6:1/4', 'D6:1/4', 'E6:1/4', 'F6:1/4',
        );

        $midiFile = $midiGenerator->convertNotesToMidi($notes);
        $midiFile->save('Tonleiter.mid');
        foreach ($midiFile->logMessages as $logMessage) {
            echo $logMessage . PHP_EOL;
        }
    }
}
