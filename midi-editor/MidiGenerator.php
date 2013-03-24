<?php

class MidiGenerator {
    public $logMessages = array();

    public function convertNotesToMidi($notes) {
        $tempoBpm = 120;
        $selectedProgramType = 40;

        $midiFile = new MidiFile();
        $midiFile->header->type = 0;
        $midiFile->header->timeDivision = 480;

        $track = (object) array('events' => array());

        $track->events[] = (object) array(
            'deltaTime' => 0,
            'type' => 'meta',
            'metaType' => 'timeSignature',
            'numerator' => 4,
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

            // timeDivision defines the number of ticks per beat (quarter note) - so multiply by 4:
            $deltaTime = ($numerator / $denominator) * 4 * $midiFile->header->timeDivision;

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
        $this->logMessages[] = $message;
    }
}
