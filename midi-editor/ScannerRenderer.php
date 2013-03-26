<?php

class ScannerRenderer {
    public $trackId = 1;
    public $channel = 0;

    public function save($filename, $midiFile) {
        file_put_contents($filename, $this->render($midiFile));
    }

    public function render($midiFile) {
        $scannerData = '';
        $playingNote = null;

        $track = $midiFile->tracks[$this->trackId];

        foreach ($track->events as $trackEvent) {
            if (isset($trackEvent->channel) && $trackEvent->channel != $this->channel) {
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
