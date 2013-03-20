<?php

require_once 'MidiFile.php';

$midiEditor = new MidiEditor();
$midiEditor->main();

class MidiEditor {
    public function main() {
        $midiFile = new MidiFile();
        $midiFile->load('Ungarischer Tanz.mid');
        $this->analyzeTracks($midiFile);
        $this->printTrackInfo($midiFile);
        $this->modifyTracks($midiFile);
        $midiFile->save('Ungarischer Tanz (output).mid');
    }

    public function analyzeTracks($midiFile) {
        foreach ($midiFile->tracks as $track) {
            $track->trackName = null;
            $track->instrumentName = null;
            $track->copyrightNotice = null;
            $track->programTypes = array();
            $track->noteCountPerChannel = array();

            foreach ($track->events as $trackEvent) {
                if ($trackEvent->type == 'meta') {
                    if ($trackEvent->metaType == 'trackName') {
                        $track->trackName = $trackEvent->text;
                    } elseif ($trackEvent->metaType == 'instrumentName') {
                        $track->instrumentName = $trackEvent->text;
                    } elseif ($trackEvent->metaType == 'copyrightNotice') {
                        $track->copyrightNotice = $trackEvent->text;
                    }
                }

                if ($trackEvent->type == 'programChange') {
                    $track->programTypes[$trackEvent->programType] = $midiFile->getProgramTypeName($trackEvent->programType);
                }

                if ($trackEvent->type == 'noteOn') {
                    if (!isset($track->noteCountPerChannel[$trackEvent->channel])) {
                        $track->noteCountPerChannel[$trackEvent->channel] = 0;
                    }
                    $track->noteCountPerChannel[$trackEvent->channel]++;
                }
            }
        }
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

    public function modifyTracks($midiFile) {
        $selectedTrackId = 1;

        foreach ($midiFile->tracks as $trackId => $track) {
            if ($trackId != $selectedTrackId) {
                unset($midiFile->tracks[$trackId]);
            }
        }
    }
}
