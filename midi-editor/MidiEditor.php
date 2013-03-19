<?php

require_once 'MidiFile.php';

MidiEditor::main();

class MidiEditor {
    public static function main() {
        $midiFile = new MidiFile();
        $midiFile->load('Ungarischer Tanz.mid');
        $midiFile->save('Ungarischer Tanz (output).mid');
    }
}
