<?php

class ScannerRenderer {
    public $selectedTrackId = 1;
    public $selectedChannel = 0;

    public function save($filename, $midiFile) {
        file_put_contents($filename, $this->render($midiFile));
    }

    public function render($midiFile) {
        $scannerData = '';
        $playingNote = null;

        $track = $midiFile->tracks[$this->selectedTrackId];

        foreach ($track->events as $trackEvent) {
            if ($trackEvent->channel != $this->selectedChannel) {
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
