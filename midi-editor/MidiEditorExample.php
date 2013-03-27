<?php

require_once 'MidiFile.php';
require_once 'MidiEditor.php';
require_once 'NoteRenderer.php';
require_once 'ScannerRenderer.php';

$midiEditorExample = new MidiEditorExample();
$midiEditorExample->main();

class MidiEditorExample {
    public function main() {
        $midiFile = new MidiFile();
        $midiFile->load('Ungarischer Tanz.mid');

        $midiEditor = new MidiEditor();
        $midiEditor->selectTrackId = 1;
        $midiEditor->selectChannel = 0;
        $midiEditor->modifyProgramType = 40;
        $midiEditor->modifyVelocity = 127;
        $midiEditor->highestNote = 'E6';
        $midiEditor->minimalPause = 100000;

        $midiEditor->analyzeTracks($midiFile);
        $this->printTrackInfo($midiFile);
        $midiEditor->modifyTracks($midiFile);

        $midiFile->save('Ungarischer Tanz (output).mid');

        foreach ($midiFile->logMessages as $logMessage) {
            echo $logMessage . PHP_EOL;
        }

        $scannerRenderer = new ScannerRenderer();
        $scannerRenderer->trackId = 1;
        $scannerRenderer->channel = 0;
        $scannerRenderer->save('Ungarischer Tanz (scanner output).bin', $midiFile);
    }

    public function printTrackInfo($midiFile) {
        foreach ($midiFile->tracks as $trackId => $track) {
            echo PHP_EOL;
            echo 'Track ' . $trackId . ($track->trackName !== null ? ' (' . $track->trackName . ')' : '') . ':' . PHP_EOL;

            if ($track->instrumentName !== null) {
                echo '  Instrument name: ' . $track->instrumentName . PHP_EOL;
            }
            if ($track->copyrightNotice !== null) {
                echo '  Copyright: ' . $track->copyrightNotice . PHP_EOL;
            }

            if ($track->programTypes !== null) {
                echo '  Used instruments: ' . implode(', ', $track->programTypes) . PHP_EOL;
            }

            if (count($track->noteCountPerChannel) > 0) {
                echo '  Note counts:' . PHP_EOL;
                foreach ($track->noteCountPerChannel as $channel => $noteCount) {
                    echo '    Channel ' . $channel . ': ' . $noteCount . ' notes' . PHP_EOL;
                }
            }
        }
    }
}
