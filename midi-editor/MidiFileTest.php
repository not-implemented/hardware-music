<?php

require_once 'MidiFile.php';

$midiFileTest = new MidiFileTest();
$midiFileTest->main();

class MidiFileTest {
    public function main() {
        foreach (glob('*.mid') as $midiFilename) {
            echo $midiFilename . PHP_EOL;

            $midiFile = new MidiFile();
            $midiFile->load($midiFilename);
            $midiFileBinary = $midiFile->render();

            foreach ($midiFile->logMessages as $logMessage) {
                echo '  ERROR: ' . $logMessage . PHP_EOL;
            }

            $midiFileOriginalBinary = file_get_contents($midiFilename);

            if ($midiFileBinary !== $midiFileOriginalBinary) {
                echo '  Files are different' . PHP_EOL;
            }
        }
    }
}
