<?php

require_once 'MidiFile.php';

$midiEditor = new MidiEditor();
$midiEditor->main();

class MidiEditor {
    public function main() {
        $midiFile = new MidiFile();
        $midiFile->load('Ungarischer Tanz.mid');
        $this->analyzeTracks($midiFile);
        $this->printTrackInfo($midiFile);
        $this->modifyTracks($midiFile);
        $this->saveScannerData($midiFile, 'Ungarischer Tanz (scanner output).bin');
        $midiFile->save('Ungarischer Tanz (output).mid');
        foreach ($midiFile->logMessages as $logMessage) {
            echo $logMessage . PHP_EOL;
        }
    }

    public function analyzeTracks($midiFile) {
        foreach ($midiFile->tracks as $track) {
            $track->trackName = null;
            $track->instrumentName = null;
            $track->copyrightNotice = null;
            $track->programTypes = array();
            $track->noteCountPerChannel = array();

            foreach ($track->events as $trackEvent) {
                if ($trackEvent->type == 'meta') {
                    if ($trackEvent->metaType == 'trackName') {
                        $track->trackName = $trackEvent->text;
                    } elseif ($trackEvent->metaType == 'instrumentName') {
                        $track->instrumentName = $trackEvent->text;
                    } elseif ($trackEvent->metaType == 'copyrightNotice') {
                        $track->copyrightNotice = $trackEvent->text;
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
        }
    }

    public function printTrackInfo($midiFile) {
        foreach ($midiFile->tracks as $trackId => $track) {
            echo PHP_EOL;
            echo 'Track ' . $trackId . ($track->trackName !== null ? ' (' . $track->trackName . ')' : '') . ':' . PHP_EOL;

            if ($track->instrumentName !== null) {
                echo '  Instrument name: ' . $track->instrumentName . PHP_EOL;
            }
            if ($track->copyrightNotice !== null) {
                echo '  Copyright: ' . $track->copyrightNotice . PHP_EOL;
            }

            if ($track->programTypes !== null) {
                echo '  Used instruments: ' . implode(', ', $track->programTypes) . PHP_EOL;
            }

            if (count($track->noteCountPerChannel) > 0) {
                echo '  Note counts:' . PHP_EOL;
                foreach ($track->noteCountPerChannel as $channel => $noteCount) {
                    echo '    Channel ' . $channel . ': ' . $noteCount . ' notes' . PHP_EOL;
                }
            }
        }
    }

    public function modifyTracks($midiFile) {
        $selectedTrackId = 1;
        $selectedProgramType = 40;
        $selectedVelocity = 127;
        $highestNote = $midiFile->mapNoteToMidi('E6');

        // select track:
        foreach ($midiFile->tracks as $trackId => $track) {
            if ($trackId != $selectedTrackId) {
                unset($midiFile->tracks[$trackId]);
            }
        }

        // we have a "one-track-MIDI" now:
        $midiFile->header->type = 0;

        foreach ($midiFile->tracks as $track) {
            $trackEvents = array();

            $deltaTimeCarryover = 0;
            $playingNote = array();
            $programTypeSet = array();

            foreach ($track->events as $trackEvent) {
                $trackEvent->deltaTime += $deltaTimeCarryover;
                $deltaTimeCarryover = 0;

                // put all notes into one channel for now:
                $trackEvent->channel = 0;

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
                    if (!isset($programTypeSet[$trackEvent->channel])) {
                        $trackEvents[] = (object) array(
                            'deltaTime' => 0,
                            'type' => 'programChange',
                            'channel' => $trackEvent->channel,
                            'programType' => $selectedProgramType,
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

                    $playingNote[$trackEvent->channel] = $trackEvent;

                    $trackEvent->velocity = $selectedVelocity;
                } elseif ($trackEvent->type == 'noteOff') {
                    if (empty($playingNote[$trackEvent->channel]) || $playingNote[$trackEvent->channel]->note !== $trackEvent->note) {
                        $deltaTimeCarryover += $trackEvent->deltaTime;
                        continue; // discard when this note is not playing
                    }

                    $playingNote[$trackEvent->channel] = null;

                    $trackEvent->velocity = 0;
                } elseif ($trackEvent->type == 'programChange') {
                    $trackEvent->programType = $selectedProgramType;
                    $programTypeSet[$trackEvent->channel] = true;
                } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'timeSignature') {
                    // keep time signature
                } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'trackName') {
                    // keep track name
                } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'setTempo') {
                    // keep tempo changes
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

    public function saveScannerData($midiFile, $filename) {
        file_put_contents($filename, $this->renderScannerData($midiFile));
    }

    public function renderScannerData($midiFile) {
        $scannerData = '';

        $track = reset($midiFile->tracks);

        foreach ($track->events as $trackEvent) {
            if ($trackEvent->channel != 0) {
                continue;
            }

            if ($trackEvent->type == 'noteOn') {
                $playingNote = $trackEvent->note;
            } elseif ($trackEvent->type == 'noteOff') {
                $playingNote = null;
            } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'setTempo') {
            }
        }

        return $scannerData;
    }
}
