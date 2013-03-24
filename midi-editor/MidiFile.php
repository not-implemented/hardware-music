<?php

/*
* Parse and write MIDI files
*
* MIDI File Format Specification: http://www.sonicspot.com/guide/midifiles.html
*/
class MidiFile {
    public $header;
    public $tracks;
    public $useNoteOffEvent;
    public $useRunningStatus;
    public $logMessages;

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

    private $programTypeNames = array(
        // Piano:
        0 => 'Acoustic Grand Piano',
        1 => 'Bright Acoustic Piano',
        2 => 'Electric Grand Piano',
        3 => 'Honky-tonk Piano',
        4 => 'Electric Piano 1',
        5 => 'Electric Piano 2',
        6 => 'Harpsichord',
        7 => 'Clavinet',

        // Chromatic Percussion:
        8 => 'Celesta',
        9 => 'Glockenspiel',
        10 => 'Music Box',
        11 => 'Vibraphone',
        12 => 'Marimba',
        13 => 'Xylophone',
        14 => 'Tubular Bells',
        15 => 'Dulcimer',

        // Organ:
        16 => 'Drawbar Organ',
        17 => 'Percussive Organ',
        18 => 'Rock Organ',
        19 => 'Church Organ',
        20 => 'Reed Organ',
        21 => 'Accordion',
        22 => 'Harmonica',
        23 => 'Tango Accordion',

        // Guitar:
        24 => 'Acoustic Guitar (nylon)',
        25 => 'Acoustic Guitar (steel)',
        26 => 'Electric Guitar (jazz)',
        27 => 'Electric Guitar (clean)',
        28 => 'Electric Guitar (muted)',
        29 => 'Overdriven Guitar',
        30 => 'Distortion Guitar',
        31 => 'Guitar Harmonics',

        // Bass:
        32 => 'Acoustic Bass',
        33 => 'Electric Bass (finger)',
        34 => 'Electric Bass (pick)',
        35 => 'Fretless Bass',
        36 => 'Slap Bass 1',
        37 => 'Slap Bass 2',
        38 => 'Synth Bass 1',
        39 => 'Synth Bass 2',

        // Strings:
        40 => 'Violin',
        41 => 'Viola',
        42 => 'Cello',
        43 => 'Contrabass',
        44 => 'Tremolo Strings',
        45 => 'Pizzicato Strings',
        46 => 'Orchestral Harp',
        47 => 'Timpani',

        // Ensemble:
        48 => 'String Ensemble 1',
        49 => 'String Ensemble 2',
        50 => 'Synth Strings 1',
        51 => 'Synth Strings 2',
        52 => 'Choir Aahs',
        53 => 'Voice Oohs',
        54 => 'Synth Choir',
        55 => 'Orchestra Hit',

        // Brass:
        56 => 'Trumpet',
        57 => 'Trombone',
        58 => 'Tuba',
        59 => 'Muted Trumpet',
        60 => 'French Horn',
        61 => 'Brass Section',
        62 => 'Synth Brass 1',
        63 => 'Synth Brass 2',

        // Reed:
        64 => 'Soprano Sax',
        65 => 'Alto Sax',
        66 => 'Tenor Sax',
        67 => 'Baritone Sax',
        68 => 'Oboe',
        69 => 'English Horn',
        70 => 'Bassoon',
        71 => 'Clarinet',

        // Pipe:
        72 => 'Piccolo',
        73 => 'Flute',
        74 => 'Recorder',
        75 => 'Pan Flute',
        76 => 'Blown bottle',
        77 => 'Shakuhachi',
        78 => 'Whistle',
        79 => 'Ocarina',

        // Synth Lead:
        80 => 'Lead 1 (square)',
        81 => 'Lead 2 (sawtooth)',
        82 => 'Lead 3 (calliope)',
        83 => 'Lead 4 (chiff)',
        84 => 'Lead 5 (charang)',
        85 => 'Lead 6 (voice)',
        86 => 'Lead 7 (fifths)',
        87 => 'Lead 8 (bass + lead)',

        // Synth Pad:
        88 => 'Pad 1 (new age)',
        89 => 'Pad 2 (warm)',
        90 => 'Pad 3 (polysynth)',
        91 => 'Pad 4 (choir)',
        92 => 'Pad 5 (bowed)',
        93 => 'Pad 6 (metallic)',
        94 => 'Pad 7 (halo)',
        95 => 'Pad 8 (sweep)',

        // Synth Effects:
        96 => 'FX 1 (rain)',
        97 => 'FX 2 (soundtrack)',
        98 => 'FX 3 (crystal)',
        99 => 'FX 4 (atmosphere)',
        100 => 'FX 5 (brightness)',
        101 => 'FX 6 (goblins)',
        102 => 'FX 7 (echoes)',
        103 => 'FX 8 (sci-fi)',

        // Ethnic:
        104 => 'Sitar',
        105 => 'Banjo',
        106 => 'Shamisen',
        107 => 'Koto',
        108 => 'Kalimba',
        109 => 'Bagpipe',
        110 => 'Fiddle',
        111 => 'Shanai',

        // Percussive:
        112 => 'Tinkle Bell',
        113 => 'Agogo',
        114 => 'Steel Drums',
        115 => 'Woodblock',
        116 => 'Taiko Drum',
        117 => 'Melodic Tom',
        118 => 'Synth Drum',
        119 => 'Reverse Cymbal',

        // Sound effects:
        120 => 'Guitar Fret Noise',
        121 => 'Breath Noise',
        122 => 'Seashore',
        123 => 'Bird Tweet',
        124 => 'Telephone Ring',
        125 => 'Helicopter',
        126 => 'Applause',
        127 => 'Gunshot',
    );

    private $noteMapping = array('C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'B', 'H');

    public function __construct() {
        $this->header = (object) array('type' => 1, 'timeDivision' => 480);
        $this->tracks = array();
        $this->useNoteOffEvent = true;
        $this->useRunningStatus = true;
        $this->logMessages = array();
    }

    public function getProgramTypeName($programType) {
        if (array_key_exists($programType, $this->programTypeNames)) {
            $programTypeName = $this->programTypeNames[$programType];
        } else {
            $programTypeName = 'Unknown (' . $programType . ')';
        }

        return $programTypeName;
    }

    public function load($filename) {
        $binaryMidi = @file_get_contents($filename);
        if ($binaryMidi === false) {
            throw new Exception('MIDI file "' . $filename . '" could not be loaded');
        }

        $this->parse($binaryMidi);
    }

    public function parse($binaryMidi) {
        $this->header = null;
        $this->tracks = array();
        $this->useNoteOffEvent = false;
        $this->useRunningStatus = false;

        $chunks = $this->splitChunks($binaryMidi);
        $headerChunk = array_shift($chunks);

        $this->header = $this->parseHeaderChunk($headerChunk);

        if ($this->header->trackCount !== count($chunks)) {
            $this->log('Track count in header "' . $this->header->trackCount . '" does not match real track count "' . count($chunks) . '"');
        }

        unset($this->header->trackCount);

        $trackId = 1;
        foreach ($chunks as $chunk) {
            $this->tracks[$trackId++] = $this->parseTrackChunk($chunk);
        }
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

        // TODO: Handle timeDivision in ticks per frame / frames per second

        return $header;
    }

    private function parseTrackChunk($chunk) {
        $track = (object) array('events' => array());
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

                $this->useRunningStatus = true;
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

                        $this->useNoteOffEvent = true;
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
                        $trackEvent->programType = $this->parseByte($chunk, $offset);
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

            $track->events[] = $trackEvent;
        }

        return $track;
    }

    public function mapNoteFromMidi($note) {
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


    public function save($filename) {
        file_put_contents($filename, $this->render());
    }

    public function render() {
        $chunks = array();

        $header = pack('nnn', $this->header->type, count($this->tracks), $this->header->timeDivision);
        $chunks[] = $header;

        foreach ($this->tracks as $track) {
            $chunks[] = $this->renderTrackChunk($track);
        }

        $binaryMidi = '';

        foreach ($chunks as $chunk) {
            $signature = $binaryMidi === '' ? 'MThd' : 'MTrk';

            $binaryMidi .= $signature . pack('N', strlen($chunk));
            $binaryMidi .= $chunk;
        }

        return $binaryMidi;
    }

    private function renderTrackChunk($track) {
        $chunk = '';

        $eventTypeMapping = array_flip($this->eventTypeMapping);
        $metaEventTypeMapping = array_flip($this->metaEventTypeMapping);
        $controllerTypeMapping = array_flip($this->controllerTypeMapping);

        $lastEventType = null;

        foreach ($track->events as $trackEvent) {
            $packet = $this->renderVariableLengthValue($trackEvent->deltaTime);

            if ($trackEvent->type == 'meta') {
                $eventType = 0xff;

                if (array_key_exists($trackEvent->metaType, $metaEventTypeMapping)) {
                    $metaEventType = $metaEventTypeMapping[$trackEvent->metaType];
                } else {
                    $this->log('Unknown meta event type "' . $trackEvent->metaType . '"');
                    $metaEventType = 0x00; // TODO: $trackEvent->metaType = 'unknown:' . $metaEventType;
                    $metaData = $trackEvent->data;
                }

                if ($trackEvent->metaType == 'sequenceNumber') {
                    $metaData = pack('n', $trackEvent->number);
                } elseif ($trackEvent->metaType == 'channelPrefix') {
                    $metaData = pack('C', $trackEvent->channel);
                } elseif ($trackEvent->metaType == 'endOfTrack') {
                    $metaData = '';
                } elseif ($trackEvent->metaType == 'setTempo') {
                    $tempo = (int) (60000000 / $trackEvent->tempoBpm);
                    $metaData = pack('N', $tempo);
                    $metaData = substr($metaData, 1); // 3 byte only
                } elseif ($trackEvent->metaType == 'smpteOffset') {
                    $metaData = $trackEvent->data;
                } elseif ($trackEvent->metaType == 'timeSignature') {
                    $metaData = '';
                    $metaData .= pack('C', $trackEvent->numerator);
                    $metaData .= pack('C', log($trackEvent->denominator, 2));
                    $metaData .= pack('C', $trackEvent->metronomeTimePerClick);
                    $metaData .= pack('C', $trackEvent->count32ndNotesPerQuarter);
                } elseif ($trackEvent->metaType == 'keySignature') {
                    $metaData = $trackEvent->data;
                } elseif ($trackEvent->metaType == 'sequencerSpecific') {
                    $metaData = $trackEvent->data;
                } else {
                    $metaData = $trackEvent->text;
                }

                $packet .= pack('C', $eventType);
                $packet .= pack('C', $metaEventType);
                $packet .= $this->renderVariableLengthValue(strlen($metaData));
                $packet .= $metaData;

                $lastEventType = null;
            } elseif ($trackEvent->type == 'sysEx' || $trackEvent->type == 'authSysEx') {
                $eventType = $trackEvent->type == 'sysEx' ? 0xf0 : 0xf7;

                $packet .= pack('C', $eventType);
                $packet .= $this->renderVariableLengthValue(strlen($trackEvent->data));
                $packet .= $trackEvent->data;

                $lastEventType = null;
            } else {
                if (!$this->useNoteOffEvent && $trackEvent->type == 'noteOff') {
                    $trackEvent = clone $trackEvent;
                    $trackEvent->type = 'noteOn';
                    $trackEvent->velocity = 0;
                }

                if (array_key_exists($trackEvent->type, $eventTypeMapping)) {
                    $eventType = $eventTypeMapping[$trackEvent->type];
                } else {
                    $this->log('Unknown event type "' . $trackEvent->type . '"');
                    $eventType = 0x8; // TODO: $trackEvent->type = 'unknown:' . $eventType;
                }

                $eventType = ($eventType << 4) | $trackEvent->channel;
                $skipEventType = false;

                if ($this->useRunningStatus && $lastEventType !== null) {
                    if ($eventType === $lastEventType) {
                        $skipEventType = true;
                    }
                }

                if (!$skipEventType) {
                    $packet .= pack('C', $eventType);
                }

                $lastEventType = $eventType;

                if ($trackEvent->type == 'noteOff') {
                    $packet .= pack('C', $this->mapNoteToMidi($trackEvent->note));
                    $packet .= pack('C', $trackEvent->velocity);
                } elseif ($trackEvent->type == 'noteOn') {
                    $packet .= pack('C', $this->mapNoteToMidi($trackEvent->note));
                    $packet .= pack('C', $trackEvent->velocity);
                } elseif ($trackEvent->type == 'noteAftertouch') {
                    $packet .= pack('C', $this->mapNoteToMidi($trackEvent->note));
                    $packet .= pack('C', $trackEvent->amount);
                } elseif ($trackEvent->type == 'controller') {
                    if (array_key_exists($trackEvent->controllerType, $controllerTypeMapping)) {
                        $controllerType = $controllerTypeMapping[$trackEvent->controllerType];
                    } else {
                        $this->log('Unknown controller type "' . $trackEvent->controllerType . '"');
                        $controllerType = 0x00; // TODO: $trackEvent->controllerType = 'unknown:' . $controllerType;
                    }

                    $packet .= pack('C', $controllerType);
                    $packet .= pack('C', $trackEvent->value);
                } elseif ($trackEvent->type == 'programChange') {
                    $packet .= pack('C', $trackEvent->programType);
                } elseif ($trackEvent->type == 'channelAftertouch') {
                    $packet .= pack('C', $trackEvent->amount);
                } elseif ($trackEvent->type == 'pitchBend') {
                    $packet .= pack('v', $trackEvent->value);
                }
            }

            $chunk .= $packet;
        }

        return $chunk;
    }

    public function mapNoteToMidi($note) {
        $noteMapping = array_flip($this->noteMapping);

        if (!preg_match('/^([CDEFGABH]#?)(\-?\d+)$/', $note, $matches)) {
            $this->log('Invalid note "' . $note . '"');
            return 0;
        }

        if (!array_key_exists($matches[1], $noteMapping)) {
            $this->log('Invalid note name "' . $note . '"');
            return 0;
        }

        $note = $noteMapping[$matches[1]];
        $octave = (int) $matches[2];

        return ($octave + 1) * 12 + $note;
    }

    private function renderVariableLengthValue($value) {
        $binaryValue = '';
        $byteFollows = false;

        while (strlen($binaryValue) < 4) {
            $byte = $value & 0x7f;
            $value = $value >> 7;

            if ($byteFollows) {
                $byte = $byte | 0x80;
            }

            $binaryValue = pack('C', $byte) . $binaryValue;

            if ($value == 0) {
                break;
            }

            $byteFollows = true;
        }

        return $binaryValue;
    }


    private function log($message) {
        $this->logMessages[] = $message;
    }
}
