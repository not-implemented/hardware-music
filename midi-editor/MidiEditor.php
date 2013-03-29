<?php

class MidiEditor {
    public $selectTrack = 0;
    public $selectChannel = 0;
    public $modifyProgramType = null;
    public $modifyVelocity = null;
    public $highestNote = 'G9';
    public $minimalPause = null;

    public function analyzeTracks($midiFile) {
        foreach ($midiFile->tracks as $track) {
            $track->trackName = null;
            $track->instrumentName = null;
            $track->copyright = null;
            $track->programTypes = array();
            $track->noteCountPerChannel = array();

            foreach ($track->events as $trackEvent) {
                if ($trackEvent->type == 'meta') {
                    if ($trackEvent->metaType == 'trackName') {
                        $track->trackName = $trackEvent->text;
                    } elseif ($trackEvent->metaType == 'instrumentName') {
                        $track->instrumentName = $trackEvent->text;
                    } elseif ($trackEvent->metaType == 'copyright') {
                        $track->copyright = $trackEvent->text;
                    }
                }

                if ($trackEvent->type == 'programChange') {
                    $track->programTypes[$trackEvent->programType] = $midiFile->getProgramTypeName($trackEvent->programType);
                }

                if ($trackEvent->type == 'noteOn') {
                    if (!isset($track->noteCountPerChannel[$trackEvent->channel])) {
                        $track->noteCountPerChannel[$trackEvent->channel] = 0;
                    }
                    $track->noteCountPerChannel[$trackEvent->channel]++;
                }
            }

            ksort($track->noteCountPerChannel);
        }
    }

    public function modifyTracks($midiFile) {
        // select track:
        foreach ($midiFile->tracks as $trackId => $track) {
            if ($trackId != $this->selectTrack) {
                unset($midiFile->tracks[$trackId]);
            }
        }

        // we have a "one-track-MIDI" now:
        $midiFile->header->type = 0;

        $highestNote = $midiFile->mapNoteToMidi($this->highestNote);

        foreach ($midiFile->tracks as $track) {
            $trackEvents = array();

            $deltaTimeCarryover = 0;
            $playingNote = array();
            $lastNote = null;
            $programTypeSet = array();
            $tempoBpm = 120;

            foreach ($track->events as $trackEvent) {
                $trackEvent->deltaTime += $deltaTimeCarryover;
                $deltaTimeCarryover = 0;

                if (isset($trackEvent->channel) && $trackEvent->channel != $this->selectChannel) {
                    $deltaTimeCarryover += $trackEvent->deltaTime;
                    continue;
                }

                // transpose high notes down:
                if ($trackEvent->type == 'noteOn' || $trackEvent->type == 'noteOff') {
                    $currentNote = $midiFile->mapNoteToMidi($trackEvent->note);

                    while ($currentNote > $highestNote) {
                        $currentNote -= 12;
                    }

                    $trackEvent->note = $midiFile->mapNoteFromMidi($currentNote);
                }

                if ($trackEvent->type == 'noteOn') {
                    // insert programChange event if not present:
                    if ($this->modifyProgramType !== null && !isset($programTypeSet[$trackEvent->channel])) {
                        $trackEvents[] = (object) array(
                            'deltaTime' => 0,
                            'type' => 'programChange',
                            'channel' => $trackEvent->channel,
                            'programType' => $this->modifyProgramType,
                        );
                    }

                    // handle simultaneously playing notes:
                    if (!empty($playingNote[$trackEvent->channel])) {
                        $playingChannelNote = $playingNote[$trackEvent->channel];

                        if ($playingChannelNote->note == $trackEvent->note) {
                            $deltaTimeCarryover += $trackEvent->deltaTime;
                            continue; // discard when same note is already playing
                        } else {
                            if ($trackEvent->deltaTime == 0) {
                                // keep highest note when both are played at the same time:
                                if ($midiFile->mapNoteToMidi($playingChannelNote->note) > $midiFile->mapNoteToMidi($trackEvent->note)) {
                                    continue; // discard current note (and keep already playing note)
                                } else {
                                    $this->removeObjectFromArray($playingChannelNote, $trackEvents);
                                }
                            } else {
                                // insert noteOff event to play only one note at a time:
                                $trackEvents[] = (object) array(
                                    'deltaTime' => $trackEvent->deltaTime,
                                    'type' => 'noteOff',
                                    'channel' => $trackEvent->channel,
                                    'note' => $playingChannelNote->note,
                                    'velocity' => 0,
                                );

                                $trackEvent->deltaTime = 0;
                            }
                        }
                    }

                    // handle minimalPause:
                    if ($this->minimalPause !== null && $lastNote !== null) {
                        // same note always needs pause:
                        if ($lastNote->note != $trackEvent->note) {
                            $duration = $midiFile->midiTimeToMicroseconds($trackEvent->deltaTime, $tempoBpm);

                            if ($duration > 0 && $duration < $this->minimalPause) {
                                $lastNote->deltaTime += $trackEvent->deltaTime;
                                $trackEvent->deltaTime = 0;
                            }
                        }
                    }

                    if ($this->modifyVelocity !== null) {
                        $trackEvent->velocity = $this->modifyVelocity;
                    }

                    $playingNote[$trackEvent->channel] = $trackEvent;
                } elseif ($trackEvent->type == 'noteOff') {
                    if (empty($playingNote[$trackEvent->channel]) || $playingNote[$trackEvent->channel]->note !== $trackEvent->note) {
                        $deltaTimeCarryover += $trackEvent->deltaTime;
                        continue; // discard when this note is not playing
                    }

                    $trackEvent->velocity = 0;

                    $playingNote[$trackEvent->channel] = null;
                    $lastNote = $trackEvent;
                } elseif ($trackEvent->type == 'programChange') {
                    if ($this->modifyProgramType !== null) {
                        $trackEvent->programType = $this->modifyProgramType;
                    }
                    $programTypeSet[$trackEvent->channel] = true;
                } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'timeSignature') {
                    // keep time signature
                } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'trackName') {
                    // keep track name
                } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'setTempo') {
                    $tempoBpm = $trackEvent->tempoBpm;
                } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'endOfTrack') {
                    // keep end of track marker
                } else {
                    $deltaTimeCarryover += $trackEvent->deltaTime;
                    continue; // discard all other events
                }

                $trackEvents[] = $trackEvent;
            }

            $track->events = $trackEvents;
        }
    }

    private function removeObjectFromArray($object, &$array) {
        $key = array_search($object, $array, true);
        if ($key !== false) {
            unset($array[$key]);
        }
    }
}
