<?php

/*
* Parse and write MIDI files
*
* MIDI File Format Specification: http://www.sonicspot.com/guide/midifiles.html
*/
class MidiFile {
    private $filename = null;
    private $header = null;
    private $tracks = null;

    private $eventTypeMapping = array(
        0x8 => 'noteOff',
        0x9 => 'noteOn',
        0xa => 'noteAftertouch',
        0xb => 'controller',
        0xc => 'programChange',
        0xd => 'channelAftertouch',
        0xe => 'pitchBend',
    );

    private $metaEventTypeMapping = array(
        0x00 => 'sequenceNumber',
        0x01 => 'textEvent',
        0x02 => 'copyrightNotice',
        0x03 => 'trackName',
        0x04 => 'instrumentName',
        0x05 => 'lyrics',
        0x06 => 'marker',
        0x07 => 'cuePoint',
        0x20 => 'channelPrefix',
        0x2f => 'endOfTrack',
        0x51 => 'setTempo',
        0x54 => 'smpteOffset',
        0x58 => 'timeSignature',
        0x59 => 'keySignature',
        0x7f => 'sequencerSpecific',
    );

    private $controllerTypeMapping = array(
        /*
        0x00 => 'Bank Select',
        0x01 => 'Modulation',
        0x02 => 'Breath Controller',
        0x04 => 'Foot Controller',
        0x05 => 'Portamento Time',
        0x06 => 'Data Entry (MSB)',
        */
        0x07 => 'mainVolume',
        /*
        0x08 => 'Balance',
        */
        0x0a => 'pan',
        /*
        0x0b => 'Expression Controller',
        0x0c => 'Effect Control 1',
        0x0d => 'Effect Control 2',
        0x10 => 'General-Purpose Controller 1',
        0x11 => 'General-Purpose Controller 2',
        0x12 => 'General-Purpose Controller 3',
        0x13 => 'General-Purpose Controller 4',
        0x40 => 'Damper pedal (sustain)',
        0x41 => 'Portamento',
        0x42 => 'Sostenuto',
        0x43 => 'Soft Pedal',
        0x44 => 'Legato Footswitch',
        0x45 => 'Hold 2',
        0x46 => 'Sound Controller 1', // default: Timber Variation
        0x47 => 'Sound Controller 2', // default: Timber/Harmonic Content
        0x48 => 'Sound Controller 3', // default: Release Time
        0x49 => 'Sound Controller 4', // default: Attack Time
        0x4a => 'Sound Controller 5',
        0x4b => 'Sound Controller 6',
        0x4c => 'Sound Controller 7',
        0x4d => 'Sound Controller 8',
        0x4e => 'Sound Controller 9',
        0x4f => 'Sound Controller 10',
        0x50 => 'General-Purpose Controller 5',
        0x51 => 'General-Purpose Controller 6',
        0x52 => 'General-Purpose Controller 7',
        0x53 => 'General-Purpose Controller 8',
        0x54 => 'Portamento Control',
        0x5b => 'Effects 1 Depth', // formerly External Effects Depth
        0x5c => 'Effects 2 Depth', // formerly Tremolo Depth
        0x5d => 'Effects 3 Depth', // formerly Chorus Depth
        0x5e => 'Effects 4 Depth', // formerly Celeste Detune
        0x5f => 'Effects 5 Depth', // formerly Phaser Depth
        0x60 => 'Data Increment',
        0x61 => 'Data Decrement',
        0x62 => 'Non-Registered Parameter Number (LSB)',
        0x63 => 'Non-Registered Parameter Number (MSB)',
        0x64 => 'Registered Parameter Number (LSB)',
        0x65 => 'Registered Parameter Number (MSB)',
        */
    );

    private $noteMapping = array('C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'B', 'H');

    public function load($filename) {
        $binaryMidi = @file_get_contents($filename);
        if ($binaryMidi === false) {
            throw new Exception('MIDI file "' . $filename . '" could not be loaded');
        }

        $this->parse($binaryMidi);

        $this->filename = $filename;
    }

    public function parse($binaryMidi) {
        $this->filename = null;
        $this->header = null;
        $this->tracks = null;

        $chunks = $this->splitChunks($binaryMidi);
        $headerChunk = array_shift($chunks);

        $this->header = $this->parseHeaderChunk($headerChunk);

        if ($this->header->trackCount !== count($chunks)) {
            $this->log('Track count in header "' . $this->header->trackCount . '" does not match real track count "' . count($chunks) . '"');
        }

        unset($this->header->trackCount);

        $this->tracks = array();

        foreach ($chunks as $chunk) {
            $this->tracks[] = $this->parseTrackChunk($chunk);
        }
    }

    public function save($filename) {
        file_put_contents($filename, $this->render());
    }

    public function render() {
        return print_r($this->tracks, true);
    }

    private function splitChunks($binaryMidi) {
        $chunks = array();
        $offset = 0;

        while ($offset < strlen($binaryMidi)) {
            if (strlen($binaryMidi) - $offset < 8) {
                $this->log('Incomplete chunk header (expected 8 bytes - got ' . (strlen($binaryMidi) - $offset) . ' bytes)');
                break;
            }

            $signature = substr($binaryMidi, $offset, 4);
            $offset += 4;
            $expectedSignature = count($chunks) == 0 ? 'MThd' : 'MTrk';

            if ($signature !== $expectedSignature) {
                $this->log('Invalid chunk signature "' . $signature . '" (expected "' . $expectedSignature . '")');
                break;
            }

            $chunkLength = unpack('Nlength', substr($binaryMidi, $offset, 4));
            $offset += 4;
            $chunkLength = $chunkLength['length'];

            if (strlen($binaryMidi) - $offset < $chunkLength) {
                $this->log('Incomplete chunk (expected ' . $chunkLength . ' bytes - got ' . (strlen($binaryMidi) - $offset) . ' bytes)');
                $chunkLength = strlen($binaryMidi) - $offset;
            }

            $chunks[] = substr($binaryMidi, $offset, $chunkLength);
            $offset += $chunkLength;
        }

        return $chunks;
    }

    private function parseHeaderChunk($headerChunk) {
        if (strlen($headerChunk) != 6) {
            $this->log('Header chunk is ' . strlen($headerChunk) . ' bytes long (expected 6 bytes)');
        }

        $header = (object) unpack('ntype/ntrackCount/ntimeDivision', $headerChunk);

        if (!in_array($header->type, array(0, 1, 2))) {
            $this->log('Invalid MIDI format type "' . $header->type . '"');
        }

        return $header;
    }

    private function parseTrackChunk($chunk) {
        $trackEvents = array();
        $offset = 0;
        $lastEventType = null;

        while ($offset < strlen($chunk)) {
            $trackEvent = new stdClass();
            $trackEvent->deltaTime = $this->parseVariableLengthValue($chunk, $offset);

            $eventType = $this->parseByte($chunk, $offset);

            // "running status" feature:
            if ($eventType & 0x80) {
                $lastEventType = $eventType;
            } elseif ($lastEventType !== null) {
                $eventType = $lastEventType;
                $offset--;
            } else {
                $this->log('No "running status" possible here');
                $eventType = 0x80; // fix with "noteOn" on channel 0
                $offset--;
            }

            if ($eventType == 0xff) {
                $trackEvent->type = 'meta';
                $metaEventType = $this->parseByte($chunk, $offset);
                $dataLength = $this->parseVariableLengthValue($chunk, $offset);
                $metaData = substr($chunk, $offset, $dataLength);
                $offset += $dataLength;

                if (array_key_exists($metaEventType, $this->metaEventTypeMapping)) {
                    $trackEvent->metaType = $this->metaEventTypeMapping[$metaEventType];

                    if ($trackEvent->metaType == 'sequenceNumber') {
                        $metaDataOffset = 0;
                        $byte1 = $this->parseByte($metaData, $metaDataOffset);
                        $byte2 = $this->parseByte($metaData, $metaDataOffset);
                        $trackEvent->number = ($byte1 << 8) | $byte2;
                    } elseif ($trackEvent->metaType == 'channelPrefix') {
                        $metaDataOffset = 0;
                        $trackEvent->channel = $this->parseByte($metaData, $metaDataOffset);
                    } elseif ($trackEvent->metaType == 'endOfTrack') {
                        // no data expected for endOfTrack
                    } elseif ($trackEvent->metaType == 'setTempo') {
                        $metaDataOffset = 0;
                        $byte1 = $this->parseByte($metaData, $metaDataOffset);
                        $byte2 = $this->parseByte($metaData, $metaDataOffset);
                        $byte3 = $this->parseByte($metaData, $metaDataOffset);
                        $tempo = ($byte1 << 16) | ($byte2 << 8) | $byte3; // microseconds per quarter note
                        $trackEvent->tempoBpm = round(60000000 / $tempo, 3);
                    } elseif ($trackEvent->metaType == 'smpteOffset') {
                        $this->log('TODO: Parse meta event type "' . $trackEvent->metaType . '"');
                        $trackEvent->data = $metaData;
                    } elseif ($trackEvent->metaType == 'timeSignature') {
                        $metaDataOffset = 0;
                        $trackEvent->numerator = $this->parseByte($metaData, $metaDataOffset);
                        $trackEvent->denominator = pow(2, $this->parseByte($metaData, $metaDataOffset));
                        $trackEvent->metronomeTimePerClick = $this->parseByte($metaData, $metaDataOffset);
                        $trackEvent->count32ndNotesPerQuarter = $this->parseByte($metaData, $metaDataOffset);
                    } elseif ($trackEvent->metaType == 'keySignature') {
                        $this->log('TODO: Parse meta event type "' . $trackEvent->metaType . '"');
                        $trackEvent->data = $metaData;
                    } elseif ($trackEvent->metaType == 'sequencerSpecific') {
                        $trackEvent->data = $metaData;
                    } else {
                        $trackEvent->text = $metaData;
                    }
                } else {
                    $this->log('Unknown meta event type "' . $metaEventType . '"');
                    $trackEvent->metaType = 'unknown:' . $metaEventType;
                    $trackEvent->data = $metaData;
                }
            } elseif ($eventType == 0xf0 || $eventType == 0xf7) {
                $trackEvent->type = $eventType == 0xf0 ? 'sysEx' : 'authSysEx';
                $dataLength = $this->parseVariableLengthValue($chunk, $offset);
                $trackEvent->data = substr($chunk, $offset, $dataLength);
                $offset += $dataLength;
            } else {
                $trackEvent->type = null;
                $trackEvent->channel = $eventType & 0x0f;
                $eventType = ($eventType & 0xf0) >> 4;

                if (array_key_exists($eventType, $this->eventTypeMapping)) {
                    $trackEvent->type = $this->eventTypeMapping[$eventType];

                    if ($trackEvent->type == 'noteOff') {
                        $trackEvent->note = $this->mapNoteFromMidi($this->parseByte($chunk, $offset));
                        $trackEvent->velocity = $this->parseByte($chunk, $offset);
                    } elseif ($trackEvent->type == 'noteOn') {
                        $trackEvent->note = $this->mapNoteFromMidi($this->parseByte($chunk, $offset));
                        $trackEvent->velocity = $this->parseByte($chunk, $offset);

                        if ($trackEvent->velocity == 0) {
                            $trackEvent->type = 'noteOff';
                        }
                    } elseif ($trackEvent->type == 'noteAftertouch') {
                        $trackEvent->note = $this->mapNoteFromMidi($this->parseByte($chunk, $offset));
                        $trackEvent->amount = $this->parseByte($chunk, $offset);
                    } elseif ($trackEvent->type == 'controller') {
                        $controllerType = $this->parseByte($chunk, $offset);

                        if (array_key_exists($controllerType, $this->controllerTypeMapping)) {
                            $trackEvent->controllerType = $this->controllerTypeMapping[$controllerType];
                        } else {
                            $this->log('Unknown controller type "' . $controllerType . '"');
                            $trackEvent->controllerType = 'unknown:' . $controllerType;
                        }

                        $trackEvent->value = $this->parseByte($chunk, $offset);
                    } elseif ($trackEvent->type == 'programChange') {
                        $trackEvent->programNumber = $this->parseByte($chunk, $offset);
                        // TODO: map
                    } elseif ($trackEvent->type == 'channelAftertouch') {
                        $trackEvent->amount = $this->parseByte($chunk, $offset);
                    } elseif ($trackEvent->type == 'pitchBend') {
                        $byte1 = $this->parseByte($chunk, $offset);
                        $byte2 = $this->parseByte($chunk, $offset);
                        $trackEvent->value = ($byte2 << 8) | $byte1;
                    }
                } else {
                    $this->log('Unknown event type "' . $eventType . '"');
                    $trackEvent->type = 'unknown:' . $eventType;
                }
            }

            $trackEvents[] = $trackEvent;
        }

        return $trackEvents;
    }

    private function mapNoteFromMidi($note) {
        $octave = (int) ($note / 12) - 1;
        $note = $note % 12;

        return $this->noteMapping[$note] . $octave;
    }

    private function parseVariableLengthValue($chunk, &$offset) {
        $value = 0;
        $valueLength = 0;

        while ($valueLength < 4) {
            $byte = $this->parseByte($chunk, $offset);

            $valueLength++;
            $byteFollows = $byte & 0x80 ? true : false;
            $byte = $byte & 0x7f;
            $value = ($value << 7) | $byte;

            if (!$byteFollows) {
                break;
            }
        }

        return $value;
    }

    private function parseByte($chunk, &$offset) {
        if ($offset >= strlen($chunk)) {
            $this->log('Unexpected end of chunk');
            return 0;
        }

        $byte = substr($chunk, $offset, 1);
        $byte = unpack('Cbyte', $byte);
        $byte = $byte['byte'];

        $offset += 1;

        return $byte;
    }

    private function log($message) {
        echo $message . PHP_EOL;
    }
}
