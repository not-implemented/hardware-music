<?php

class BinaryNoteRenderer extends NoteRenderer {
    public function render($midiFile) {
        $scannerData = '';

        $notes = $this->renderNotes($midiFile);

        foreach ($notes as $note) {
            $packet = '';
            $packet .= pack('L', $note->pause);
            $packet .= pack('L', $note->frequency);
            $packet .= pack('L', $note->duration);

            $scannerData .= $packet;
        }

        return $scannerData;
    }
}
