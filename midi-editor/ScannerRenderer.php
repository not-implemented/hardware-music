<?php

class ScannerRenderer {
    public function save($midiFile, $filename) {
        file_put_contents($filename, $this->render($midiFile));
    }

    public function render($midiFile) {
        $scannerData = '';

        $track = reset($midiFile->tracks);

        foreach ($track->events as $trackEvent) {
            if ($trackEvent->channel != 0) {
                continue;
            }

            if ($trackEvent->type == 'noteOn') {
                $playingNote = $trackEvent->note;
            } elseif ($trackEvent->type == 'noteOff') {
                $playingNote = null;
            } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'setTempo') {
            }
        }

        return $scannerData;
    }
}
