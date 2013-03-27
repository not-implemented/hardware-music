Hardware-Music
===============
Toolset for making music on old hardware with MIDI files.

midi-editor
------------
A compact MIDI parser/writer implementation in PHP for manipulation of MIDI files.

Features MidiEditor:
- Automatic manipulation of MIDI files to fit old hardware
- Track/Channel selection
- Change instrument
- Change volume
- Transpose high notes down
- Remove short pauses (for fluent playing)

Features ScannerRenderer:
- Render notes for playing them on an old scanner stepper motor
- Tune notes (adjust delay overhead)

Features MidiGenerator:
- Convert notes in form "D5:1/8 D5:1/8 H4:1/8 C5:1/4 A4:1/4" to MIDI files
- Set tempo in bpm
- Set instrument
