<?php

abstract class NoteRenderer {
    public $trackId = 1;
    public $channel = 0;

    public function save($filename, $midiFile) {
        file_put_contents($filename, $this->render($midiFile));
    }

    abstract public function render($midiFile);

    protected function renderNotes($midiFile) {
        $track = $midiFile->tracks[$this->trackId];

        $notes = array();

        $tempoBpm = 120;
        $durationCarryover = 0;
        $playingNote = (object) array(
            'pause' => 0,
            'note' => null,
            'duration' => 0,
        );

        foreach ($track->events as $trackEvent) {
            // duration in microseconds:
            $durationCarryover += (int) (($trackEvent->deltaTime / $midiFile->header->timeDivision) * (60 * 1000000 / $tempoBpm));

            if (isset($trackEvent->channel) && $trackEvent->channel != $this->channel) {
                continue;
            }

            if ($trackEvent->type == 'noteOn') {
                if ($playingNote->note !== null) {
                    continue;
                }

                $playingNote->pause += $durationCarryover;
                $playingNote->note = $trackEvent->note;

                $durationCarryover = 0;
            } elseif ($trackEvent->type == 'noteOff') {
                if ($playingNote->note !== $trackEvent->note) {
                    continue;
                }

                $playingNote->duration += $durationCarryover;
                $notes[] = $playingNote;

                $durationCarryover = 0;
                $playingNote = (object) array(
                    'pause' => 0,
                    'note' => null,
                    'duration' => 0,
                );
            } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'setTempo') {
                $tempoBpm = $trackEvent->tempoBpm;
            }
        }

        return $notes;
    }
}
