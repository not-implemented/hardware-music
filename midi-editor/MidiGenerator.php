<?php

require_once 'MidiFile.php';

$midiGenerator = new MidiGenerator();
$midiGenerator->main();

class MidiGenerator {
    public function main() {
        // Sieben Tage lang:
        $notes = array(
            'D5:1/4', 'D5:1/4', 'H4:1/4', 'C5:1/2', 'A4:1/2',
            'A4:1/4', 'D5:1/4', 'D5:1/4', 'C5:1/4', 'H4:1/4',
            'D5:1/4', 'D5:1/8', 'C5:1/8', 'H4:1/4', 'C5:1/2', 'A4:1/2',
            'H4:1/4', 'G4:1/4', 'A4:1/1',

            'A4:1/4', 'C5:1/4', 'D5:1/4', 'E5:1/2', 'E5:1/2', 'F5:1/4', 'D5:1/4', 'E5:1/1',

            'D5:1/4', 'D5:1/8', 'C5:1/8', 'H4:1/4', 'C5:1/2', 'A4:1/2',
            'A4:1/4', 'D5:1/4', 'D5:1/4', 'C5:1/4', 'H4:1/4',
            'D5:1/4', 'D5:1/8', 'C5:1/8', 'H4:1/4', 'C5:1/2', 'A4:1/2',
            'H4:1/4', 'G4:1/4', 'A4:1/1',
        );

        $midiFile = $this->convertNotesToMidi($notes);
        $midiFile->save('Sieben Tage lang.mid');

        // Tonleiter:
        $notes = array(
            'E1:1/4', 'F1:1/4', 'G1:1/4', 'A1:1/4', 'H1:1/4',
            'C2:1/4', 'D2:1/4', 'E2:1/4', 'F2:1/4', 'G2:1/4', 'A2:1/4', 'H2:1/4',
            'C3:1/4', 'D3:1/4', 'E3:1/4', 'F3:1/4', 'G3:1/4', 'A3:1/4', 'H3:1/4',
            'C4:1/4', 'D4:1/4', 'E4:1/4', 'F4:1/4', 'G4:1/4', 'A4:1/4', 'H4:1/4',
            'C5:1/4', 'D5:1/4', 'E5:1/4', 'F5:1/4', 'G5:1/4', 'A5:1/4', 'H5:1/4',
            'C6:1/4', 'D6:1/4', 'E6:1/4', 'F6:1/4',
        );

        $midiFile = $this->convertNotesToMidi($notes);
        $midiFile->save('Tonleiter.mid');
    }

    public function convertNotesToMidi($notes) {
        $tempoBpm = 120;
        $selectedProgramType = 40;

        $midiFile = new MidiFile();
        $track = (object) array('events' => array());

        $midiFile->header->timeDivision = 480;

        $track->events[] = (object) array(
            'deltaTime' => 0,
            'type' => 'meta',
            'metaType' => 'timeSignature',
            'numerator' => 2,
            'denominator' => 4,
            'metronomeTimePerClick' => 24,
            'count32ndNotesPerQuarter' => 8,
        );

        $track->events[] = (object) array(
            'deltaTime' => 0,
            'type' => 'meta',
            'metaType' => 'setTempo',
            'tempoBpm' => $tempoBpm,
        );

        $track->events[] = (object) array(
            'deltaTime' => 0,
            'type' => 'programChange',
            'channel' => 0,
            'programType' => $selectedProgramType,
        );

        $deltaTimeCarryover = 0;

        foreach ($notes as $note) {
            if (!preg_match('/^([CDEFGABH]#?\-?\d+|P):(\d+)\/(\d+)$/', $note, $matches)) {
                $this->log('Ignoring invalid note "' . $note . '"');
                continue;
            }

            $note = $matches[1];
            $numerator = (int) $matches[2];
            $denominator = (int) $matches[3];

            $deltaTime = ($numerator / $denominator) * $midiFile->header->timeDivision;

            if ($note == 'P') {
                $deltaTimeCarryover += $deltaTime;
                continue;
            }

            $track->events[] = (object) array(
                'deltaTime' => $deltaTimeCarryover,
                'type' => 'noteOn',
                'channel' => 0,
                'note' => $note,
                'velocity' => 127,
            );

            $deltaTimeCarryover = 0;

            $track->events[] = (object) array(
                'deltaTime' => $deltaTime,
                'type' => 'noteOff',
                'channel' => 0,
                'note' => $note,
                'velocity' => 0,
            );
        }

        $track->events[] = (object) array(
            'deltaTime' => $midiFile->header->timeDivision,
            'type' => 'meta',
            'metaType' => 'endOfTrack',
        );

        $midiFile->tracks[1] = $track;

        return $midiFile;
    }

    private function log($message) {
        echo $message . PHP_EOL;
    }
}
