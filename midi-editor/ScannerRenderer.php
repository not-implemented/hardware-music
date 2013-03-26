<?php

class ScannerRenderer extends NoteRenderer {
    public $microsecondsOverhead = 76;

    public function render($midiFile) {
        $scannerData = '';

        $notes = $this->renderNotes($midiFile);

        $currentPosition = 0;
        $direction = 1;

        foreach ($notes as $note) {
            $pause = $note->pause;
            $steps = round($note->duration * $note->frequency / 1000000);
            $delay = max(round(1000000 / $note->frequency) - $this->microsecondsOverhead, 0);

            $scannerData .= '{' . $pause .', ' . $steps . ', ' . $delay .', ' . ($direction == 1 ? '1' : '0') .'},' . PHP_EOL;

            $currentPosition += $steps * $direction;
            if ($currentPosition >= 2000) {
                $direction = -1;
            } elseif ($currentPosition <= 0) {
                $direction = 1;
            }
        }

        return $scannerData;
    }
}
