<?php

class MidiGenerator {
    public $timeDivision = 480;
    public $tempoBpm = 120;
    public $program = 0;
    public $velocity = 127;

    public function convertNotesToMidi($notes) {
        $midiFile = new MidiFile();
        $midiFile->header->type = 0;
        $midiFile->header->timeDivision = $this->timeDivision;

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
            'tempoBpm' => $this->tempoBpm,
        );

        $track->events[] = (object) array(
            'deltaTime' => 0,
            'type' => 'programChange',
            'channel' => 0,
            'number' => $this->program,
        );

        $deltaTimeCarryover = 0;

        foreach ($notes as $note) {
            if (!preg_match('/^([CDEFGABH]#?\-?\d+|P):(\d+)\/(\d+)$/', $note, $matches)) {
                throw new Exception('Invalid note "' . $note . '"');
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
                'velocity' => $this->velocity,
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

        $midiFile->tracks[] = $track;

        return $midiFile;
    }
}
