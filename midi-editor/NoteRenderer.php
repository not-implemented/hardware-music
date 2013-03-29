<?php

abstract class NoteRenderer {
    public $track = 0;
    public $channel = 0;
    public $concertPitchFrequency = 440;

    public function save($filename, $midiFile) {
        file_put_contents($filename, $this->render($midiFile));
    }

    abstract public function render($midiFile);

    protected function renderNotes($midiFile) {
        $track = $midiFile->tracks[$this->track];

        $notes = array();

        $tempoBpm = 120;
        $durationCarryover = 0;
        $playingNote = (object) array(
            'pause' => 0,
            'note' => null,
            'frequency' => null,
            'duration' => 0,
        );

        $concertPitchMidiNote = $midiFile->mapNoteToMidi('A4');
        $diffFactor = pow(2, 1/12); // note-to-note frequency difference factor: 12th root of 2

        foreach ($track->events as $trackEvent) {
            // duration in microseconds:
            $durationCarryover += $midiFile->midiTimeToMicroseconds($trackEvent->deltaTime, $tempoBpm);

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

                $midiNote = $midiFile->mapNoteToMidi($playingNote->note);
                $playingNote->frequency = round($this->concertPitchFrequency * pow($diffFactor, $midiNote - $concertPitchMidiNote), 3);

                $notes[] = $playingNote;

                $durationCarryover = 0;
                $playingNote = (object) array(
                    'pause' => 0,
                    'note' => null,
                    'frequency' => null,
                    'duration' => 0,
                );
            } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'setTempo') {
                $tempoBpm = $trackEvent->tempoBpm;
            }
        }

        return $notes;
    }
}
