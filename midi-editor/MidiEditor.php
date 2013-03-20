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
        $midiFile->save('Ungarischer Tanz (output).mid');
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

        // set programType, discard unknown events:
        foreach ($midiFile->tracks as $track) {
            $trackEvents = array();

            $programTypeSet = array();
            $playingNote = array();
            $deltaTimeRest = 0;

            foreach ($track->events as $trackEvent) {
                $trackEvent->deltaTime += $deltaTimeRest;
                $deltaTimeRest = 0;

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

                    if (!empty($playingNote[$trackEvent->channel])) {
                        $playingChannelNote = $playingNote[$trackEvent->channel];

                        if ($playingChannelNote != $trackEvent->note) {
                            if ($trackEvent->deltaTime == 0) {
                                if ($midiFile->mapNoteToMidi($playingChannelNote) > $midiFile->mapNoteToMidi($trackEvent->note)) {
                                    continue;
                                } else {
                                    // TODO: Use a reference to playing note:
                                    array_pop($trackEvents);
                                }
                            } else {
                                // insert noteOff event to play only one note at a time:
                                $trackEvents[] = (object) array(
                                    'deltaTime' => $trackEvent->deltaTime,
                                    'type' => 'noteOff',
                                    'channel' => $trackEvent->channel,
                                    'note' => $playingChannelNote,
                                    'velocity' => 0,
                                );

                                $trackEvent->deltaTime = 0;
                            }
                        }
                    }

                    $playingNote[$trackEvent->channel] = $trackEvent->note;

                    $trackEvent->velocity = $selectedVelocity;
                } elseif ($trackEvent->type == 'noteOff') {
                    if (empty($playingNote[$trackEvent->channel]) || $playingNote[$trackEvent->channel] !== $trackEvent->note) {
                        $deltaTimeRest += $trackEvent->deltaTime;
                        continue; // discard useless noteOff events
                    }

                    $playingNote[$trackEvent->channel] = null;

                    $trackEvent->velocity = 0;
                } elseif ($trackEvent->type == 'programChange') {
                    $trackEvent->programType = $selectedProgramType;
                    $programTypeSet[$trackEvent->channel] = true;
                } elseif ($trackEvent->type == 'meta' && $trackEvent->metaType == 'setTempo') {
                } else {
                    // discard all other events:
                    continue;
                }

                $trackEvents[] = $trackEvent;
            }

            $track->events = $trackEvents;
        }
    }
}
