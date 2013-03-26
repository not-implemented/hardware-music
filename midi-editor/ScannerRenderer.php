<?php

class ScannerRenderer extends NoteRenderer {
    public function render($midiFile) {
        $scannerData = '';

        $notes = $this->renderNotes($midiFile);

        foreach ($notes as $note) {
            $scannerData .= json_encode($note) . PHP_EOL;
        }

        return $scannerData;
    }
}
