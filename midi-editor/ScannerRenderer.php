<?php

class ScannerRenderer extends NoteRenderer {
    public function render($midiFile) {
        $scannerData = '';

        $notes = $this->renderNotes($midiFile);

        $concertPitchMidiNote = $midiFile->mapNoteToMidi('A4');
        $concertPitchFrequency = 440;
        $diffFactor = pow(2, 1/12); // note-to-note frequency difference factor: 12th root of 2

        $currentPosition = 0;
        $direction = 1;

        foreach ($notes as $note) {
            $midiNote = $midiFile->mapNoteToMidi($note->note);
            $frequency = round($concertPitchFrequency * pow($diffFactor, $midiNote - $concertPitchMidiNote), 3);

            $pause = $note->pause;
            $steps = round($note->duration * $frequency / 1000000);
            $delay = round(1000000 / $frequency);

            $scannerData .= '{' . $pause .', ' . $steps . ', ' . $delay .', ' . ($direction == 1 ? '1' : '0') .'},' . PHP_EOL;

            $currentPosition += $steps * $direction;
            if ($currentPosition >= 500) {
                $direction = -1;
            } elseif ($currentPosition <= 0) {
                $direction = 1;
            }
        }

        return $scannerData;
    }
}
