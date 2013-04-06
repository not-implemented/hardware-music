<?php

/*
* Parse and write MIDI files
*
* MIDI File Format Specification: http://www.sonicspot.com/guide/midifiles.html
*/
class MidiFile {
    public $header;
    public $tracks;
    public $appendage;

    public $charset;
    public $useNoteOffEvent;
    public $useRunningStatus;

    public $logMessages;

    private $eventTypeMapping = array(
        // Voice category events (second nibble contains channel):
        0x8 => 'noteOff',
        0x9 => 'noteOn',
        0xa => 'polyKeyPressure',
        0xb => 'controlChange',
        0xc => 'programChange',
        0xd => 'channelKeyPressure',
        0xe => 'pitchBendChange',

        // System common category events:
        0xf0 => 'sysEx',
        0xf1 => 'mtcQuarterFrame',
        0xf2 => 'songPositionPointer',
        0xf3 => 'songSelect',
        0xf6 => 'tuneRequest',
        0xf7 => 'endSysEx',

        // Realtime category events:
        0xf8 => 'timingClock',
        0xfa => 'start',
        0xfb => 'continue',
        0xfc => 'stop',
        0xfe => 'activeSensing',
        0xff => 'meta',
    );

    private $metaEventTypeMapping = array(
        0x00 => 'sequenceNumber',
        0x01 => 'text',
        0x02 => 'copyright',
        0x03 => 'trackName',
        0x04 => 'instrumentName',
        0x05 => 'lyric',
        0x06 => 'marker',
        0x07 => 'cuePoint',
        0x08 => 'programName',
        0x09 => 'deviceName',
        0x20 => 'channelPrefix',
        0x21 => 'portPrefix',
        0x2f => 'endOfTrack',
        0x51 => 'setTempo',
        0x54 => 'smpteOffset',
        0x58 => 'timeSignature',
        0x59 => 'keySignature',
        0x7f => 'sequencerSpecific',
    );

    private $controlMapping = array(
        0x00 => 'bankSelect',
        0x01 => 'modulation',
        0x02 => 'breathController',
        0x04 => 'footController',
        0x05 => 'portamentoTime',
        0x06 => 'dataEntry',
        0x07 => 'mainVolume',
        0x08 => 'balance',
        0x0a => 'pan',
        0x0b => 'expression',
        0x0c => 'effectControl1',
        0x0d => 'effectControl2',
        0x10 => 'generalPurposeController1',
        0x11 => 'generalPurposeController2',
        0x12 => 'generalPurposeController3',
        0x13 => 'generalPurposeController4',

        // 0x20-0x3f: LSB for Controllers 0x00-0x1f (rarely implemented)
        0x20 => 'bankSelect_lsb',
        0x21 => 'modulation_lsb',
        0x22 => 'breathController_lsb',
        0x24 => 'footController_lsb',
        0x25 => 'portamentoTime_lsb',
        0x26 => 'dataEntry_lsb',
        0x27 => 'mainVolume_lsb',
        0x28 => 'balance_lsb',
        0x2a => 'pan_lsb',
        0x2b => 'expression_lsb',
        0x2c => 'effectControl1_lsb',
        0x2d => 'effectControl2_lsb',
        0x30 => 'generalPurposeController1_lsb',
        0x31 => 'generalPurposeController2_lsb',
        0x32 => 'generalPurposeController3_lsb',
        0x33 => 'generalPurposeController4_lsb',

        0x40 => 'sustain',
        0x41 => 'portamento',
        0x42 => 'sostenuto',
        0x43 => 'softPedal',
        0x44 => 'legatoFootswitch',
        0x45 => 'hold2',
        0x46 => 'soundController1', // default: Timber Variation
        0x47 => 'soundController2', // default: Timber/Harmonic Content
        0x48 => 'soundController3', // default: Release Time
        0x49 => 'soundController4', // default: Attack Time
        0x4a => 'soundController5',
        0x4b => 'soundController6',
        0x4c => 'soundController7',
        0x4d => 'soundController8',
        0x4e => 'soundController9',
        0x4f => 'soundController10',
        0x50 => 'generalPurposeController5',
        0x51 => 'generalPurposeController6',
        0x52 => 'generalPurposeController7',
        0x53 => 'generalPurposeController8',
        0x54 => 'portamentoControl',
        0x5b => 'effect1Depth', // formerly External Effects Depth
        0x5c => 'effect2Depth', // formerly Tremolo Depth
        0x5d => 'effect3Depth', // formerly Chorus Depth
        0x5e => 'effect4Depth', // formerly Celeste Detune
        0x5f => 'effect5Depth', // formerly Phaser Depth
        0x60 => 'dataIncrement',
        0x61 => 'dataDecrement',
        0x62 => 'nonRegisteredParameterNumber_lsb',
        0x63 => 'nonRegisteredParameterNumber', // MSB
        0x64 => 'registeredParameterNumber_lsb',
        0x65 => 'registeredParameterNumber', // MSB

        0x78 => 'allSoundOff',
        0x79 => 'resetAllControllers',
        0x7a => 'localControl',
        0x7b => 'allNotesOff',
        0x7c => 'omniOff',
        0x7d => 'omniOn',
        0x7e => 'monoMode',
        0x7f => 'polyMode',
    );

    private $programNames = array(
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

    private $frameRateMapping = array(
        0 => '24fps',
        1 => '25fps',
        2 => '29.97fps',
        3 => '30fps',
    );

    private $noteMapping = array('C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'B', 'H');

    public function __construct() {
        $this->header = (object) array('type' => 1, 'timeDivision' => 480);
        $this->tracks = array();
        $this->appendage = null;

        $this->charset = 'pass';
        $this->useNoteOffEvent = true;
        $this->useRunningStatus = true;

        $this->logMessages = array();
    }

    public function midiTimeToMicroseconds($midiTime, $tempoBpm = 120) {
        return (int) (($midiTime / $this->header->timeDivision) * (60 * 1000000 / $tempoBpm));
    }

    public function getProgramName($number) {
        if (array_key_exists($number, $this->programNames)) {
            $programName = $this->programNames[$number];
        } else {
            $programName = 'Unknown (' . $number . ')';
        }

        return $programName;
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
        $this->appendage = null;

        $this->useNoteOffEvent = false;
        $this->useRunningStatus = false;

        $offset = 0;

        $headerChunk = $this->parseChunk('MThd', $binaryMidi, $offset);
        if ($headerChunk === null) {
            throw new Exception('MIDI header chunk not found');
        }

        $this->header = $this->parseHeaderChunk($headerChunk);

        while (count($this->tracks) < $this->header->trackCount) {
            $chunk = $this->parseChunk('MTrk', $binaryMidi, $offset);
            if ($chunk === null) {
                break;
            }

            $this->tracks[] = $this->parseTrackChunk($chunk);
        }

        if (count($this->tracks) !== $this->header->trackCount) {
            $this->log('Expected ' . $this->header->trackCount . ' tracks - got ' . count($this->tracks) . ' tracks');
        }
        unset($this->header->trackCount); // avoid redundant information

        if (count($this->tracks) > 1 && $this->header->type == 0) {
            $this->header->type = 1;
            $this->log('MIDI type 0 file with ' . count($this->tracks) . ' tracks - changed type to 1');
        }

        if (strlen($binaryMidi) - $offset > 0) {
            $this->appendage = substr($binaryMidi, $offset);
        }
    }

    private function parseChunk($expectedSignature, $binaryMidi, &$offset) {
        if ($offset >= strlen($binaryMidi)) {
            return null; // EOF
        }

        if (strlen($binaryMidi) - $offset < 8) {
            $this->log('Incomplete chunk header (expected 8 bytes - got ' . (strlen($binaryMidi) - $offset) . ' bytes)');
            return null;
        }

        $signature = substr($binaryMidi, $offset, 4);
        if ($signature !== $expectedSignature) {
            $this->log('Invalid chunk signature "' . $signature . '" (expected "' . $expectedSignature . '")');
            return null;
        }

        $chunkLength = unpack('Nlength', substr($binaryMidi, $offset + 4, 4));
        $chunkLength = $chunkLength['length'];
        $offset += 8;

        if (strlen($binaryMidi) - $offset < $chunkLength) {
            $this->log('Incomplete chunk (expected ' . $chunkLength . ' bytes - got ' . (strlen($binaryMidi) - $offset) . ' bytes)');
            $chunkLength = strlen($binaryMidi) - $offset;
        }

        $chunk = substr($binaryMidi, $offset, $chunkLength);
        $offset += $chunkLength;

        return $chunk;
    }

    private function parseHeaderChunk($headerChunk) {
        if (strlen($headerChunk) != 6) {
            $this->log('Header chunk is ' . strlen($headerChunk) . ' bytes long (expected 6 bytes)');
        }

        $header = (object) unpack('ntype/ntrackCount/ntimeDivision', $headerChunk);

        if (!in_array($header->type, array(0, 1, 2))) {
            $this->log('Invalid MIDI format type "' . $header->type . '"');
        }

        if ($header->timeDivision & 0x8000) {
            $this->log('TODO: Handle timeDivision in ticks per frame / frames per second');
        }

        return $header;
    }

    private function parseTrackChunk($chunk) {
        $track = (object) array('events' => array());
        $offset = 0;
        $lastEventType = null;

        while ($offset < strlen($chunk)) {
            $trackEvent = new stdClass();
            $trackEvent->deltaTime = $this->parseVariableLengthValue($chunk, $offset);
            $trackEvent->type = null;

            $eventType = $this->parseByte($chunk, $offset);

            // "running status" feature:
            if ($eventType <= 0x7f) {
                if ($lastEventType !== null) {
                    $eventType = $lastEventType;
                    $offset--;

                    $this->useRunningStatus = true;
                } else {
                    $this->log('No "running status" possible here');
                    continue;
                }
            }

            // Voice category event (second nibble contains channel):
            if ($eventType >= 0x80 && $eventType <= 0xef) {
                $lastEventType = $eventType;
                $trackEvent->channel = $eventType & 0x0f;
                $eventType = ($eventType & 0xf0) >> 4;
            }

            if (array_key_exists($eventType, $this->eventTypeMapping)) {
                $trackEvent->type = $this->eventTypeMapping[$eventType];
            } else {
                $this->log('Unknown event type "' . $eventType . '"');
                $trackEvent->type = 'id:' . $eventType;
            }

            if ($trackEvent->type == 'meta') {
                $metaEventType = $this->parseByte($chunk, $offset);
                $dataLength = $this->parseVariableLengthValue($chunk, $offset);
                $metaData = substr($chunk, $offset, $dataLength);
                $metaDataOffset = 0;
                $offset += $dataLength;

                if (array_key_exists($metaEventType, $this->metaEventTypeMapping)) {
                    $trackEvent->metaType = $this->metaEventTypeMapping[$metaEventType];

                    if ($trackEvent->metaType == 'sequenceNumber') {
                        $byte1 = $this->parseByte($metaData, $metaDataOffset);
                        $byte2 = $this->parseByte($metaData, $metaDataOffset);
                        $trackEvent->number = ($byte1 << 8) | $byte2;
                    } elseif ($trackEvent->metaType == 'channelPrefix') {
                        $trackEvent->channel = $this->parseByte($metaData, $metaDataOffset);
                    } elseif ($trackEvent->metaType == 'portPrefix') {
                        $trackEvent->port = $this->parseByte($metaData, $metaDataOffset);
                    } elseif ($trackEvent->metaType == 'endOfTrack') {
                        // no data expected for endOfTrack
                    } elseif ($trackEvent->metaType == 'setTempo') {
                        $byte1 = $this->parseByte($metaData, $metaDataOffset);
                        $byte2 = $this->parseByte($metaData, $metaDataOffset);
                        $byte3 = $this->parseByte($metaData, $metaDataOffset);
                        $tempo = ($byte1 << 16) | ($byte2 << 8) | $byte3; // microseconds per quarter note
                        $trackEvent->tempoBpm = round(60000000 / $tempo, 6);
                    } elseif ($trackEvent->metaType == 'smpteOffset') {
                        $byte = $this->parseByte($metaData, $metaDataOffset);
                        $trackEvent->frameRate = $this->frameRateMapping[$byte >> 5];
                        $trackEvent->hours = $byte & 0x1f;
                        $trackEvent->minutes = $this->parseByte($metaData, $metaDataOffset);
                        $trackEvent->seconds = $this->parseByte($metaData, $metaDataOffset);
                        $trackEvent->frames = $this->parseByte($metaData, $metaDataOffset);
                        $trackEvent->subFrames = $this->parseByte($metaData, $metaDataOffset);
                    } elseif ($trackEvent->metaType == 'timeSignature') {
                        $trackEvent->numerator = $this->parseByte($metaData, $metaDataOffset);
                        $trackEvent->denominator = pow(2, $this->parseByte($metaData, $metaDataOffset));
                        $trackEvent->metronomeTimePerClick = $this->parseByte($metaData, $metaDataOffset);
                        $trackEvent->count32ndNotesPerQuarter = $this->parseByte($metaData, $metaDataOffset);
                    } elseif ($trackEvent->metaType == 'keySignature') {
                        $byte = unpack('cbyte', pack('C', $this->parseByte($metaData, $metaDataOffset)));
                        $trackEvent->fifths = $byte['byte'];
                        $trackEvent->mode = $this->parseByte($metaData, $metaDataOffset) == 0 ? 'major' : 'minor';
                    } elseif ($metaEventType >= 0x01 && $metaEventType <= 0x0f) {
                        $trackEvent->text = rtrim($metaData, "\0"); // some applications save the NUL-Byte
                        $trackEvent->text = mb_convert_encoding($trackEvent->text, 'utf-8', $this->charset);
                    } else {
                        $trackEvent->data = $metaData;
                    }
                } else {
                    $this->log('Unknown meta event type "' . $metaEventType . '"');
                    $trackEvent->metaType = 'id:' . $metaEventType;
                    $trackEvent->data = $metaData;
                }
            } elseif ($eventType >= 0xf0 && $eventType <= 0xfe) {
                if ($eventType <= 0xf7) {
                    $lastEventType = null; // System common category event resets running status
                }

                $dataLength = $this->parseVariableLengthValue($chunk, $offset);
                $trackEvent->data = substr($chunk, $offset, $dataLength);
                $offset += $dataLength;
            } elseif ($trackEvent->type == 'noteOff') {
                $trackEvent->note = $this->mapNoteFromMidi($this->parseByte($chunk, $offset));
                $trackEvent->velocity = $this->parseByte($chunk, $offset);

                $this->useNoteOffEvent = true;
            } elseif ($trackEvent->type == 'noteOn') {
                $trackEvent->note = $this->mapNoteFromMidi($this->parseByte($chunk, $offset));
                $trackEvent->velocity = $this->parseByte($chunk, $offset);

                if ($trackEvent->velocity == 0) {
                    $trackEvent->type = 'noteOff';
                }
            } elseif ($trackEvent->type == 'polyKeyPressure') {
                $trackEvent->note = $this->mapNoteFromMidi($this->parseByte($chunk, $offset));
                $trackEvent->pressure = $this->parseByte($chunk, $offset);
            } elseif ($trackEvent->type == 'controlChange') {
                $control = $this->parseByte($chunk, $offset);

                if (array_key_exists($control, $this->controlMapping)) {
                    $trackEvent->control = $this->controlMapping[$control];
                } else {
                    $this->log('Unknown control "' . $control . '"');
                    $trackEvent->control = 'id:' . $control;
                }

                $trackEvent->value = $this->parseByte($chunk, $offset);
            } elseif ($trackEvent->type == 'programChange') {
                $trackEvent->number = $this->parseByte($chunk, $offset);
            } elseif ($trackEvent->type == 'channelKeyPressure') {
                $trackEvent->pressure = $this->parseByte($chunk, $offset);
            } elseif ($trackEvent->type == 'pitchBendChange') {
                $byte1 = $this->parseByte($chunk, $offset);
                $byte2 = $this->parseByte($chunk, $offset);
                $trackEvent->value = ($byte2 << 8) | $byte1;
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

    private function parseByte($data, &$offset) {
        if ($offset >= strlen($data)) {
            $this->log('Unexpected end of data');
            return 0;
        }

        $byte = substr($data, $offset, 1);
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

        $headerChunk = pack('nnn', $this->header->type, count($this->tracks), $this->header->timeDivision);
        $chunks[] = $headerChunk;

        foreach ($this->tracks as $track) {
            $chunks[] = $this->renderTrackChunk($track);
        }

        $binaryMidi = '';

        foreach ($chunks as $chunk) {
            $signature = $binaryMidi === '' ? 'MThd' : 'MTrk';

            $binaryMidi .= $signature . pack('N', strlen($chunk));
            $binaryMidi .= $chunk;
        }

        if ($this->appendage !== null) {
            $binaryMidi .= $this->appendage;
        }

        return $binaryMidi;
    }

    private function renderTrackChunk($track) {
        $chunk = '';

        $eventTypeMapping = array_flip($this->eventTypeMapping);
        $metaEventTypeMapping = array_flip($this->metaEventTypeMapping);
        $controlMapping = array_flip($this->controlMapping);
        $frameRateMapping = array_flip($this->frameRateMapping);

        $lastEventType = null;

        foreach ($track->events as $trackEvent) {
            $packet = $this->renderVariableLengthValue($trackEvent->deltaTime);

            if (!$this->useNoteOffEvent && $trackEvent->type == 'noteOff') {
                $trackEvent = clone $trackEvent;
                $trackEvent->type = 'noteOn';
                $trackEvent->velocity = 0;
            }

            if (array_key_exists($trackEvent->type, $eventTypeMapping)) {
                $eventType = $eventTypeMapping[$trackEvent->type];
            } elseif (preg_match('/^id:(\d+)$/', $trackEvent->type, $matches)) {
                $eventType = (int) $matches[1];
            } else {
                $this->log('Unknown event type "' . $trackEvent->type . '"');
                continue;
            }

            if ($eventType >= 0x8 && $eventType <= 0xe) {
                $eventType = ($eventType << 4) | $trackEvent->channel;
            }

            if ($trackEvent->type == 'meta') {
                if (array_key_exists($trackEvent->metaType, $metaEventTypeMapping)) {
                    $metaEventType = $metaEventTypeMapping[$trackEvent->metaType];
                } elseif (preg_match('/^id:(\d+)$/', $trackEvent->metaType, $matches)) {
                    $metaEventType = (int) $matches[1];
                    $metaData = $trackEvent->data;
                } else {
                    $this->log('Unknown meta event type "' . $trackEvent->metaType . '"');
                    continue;
                }

                if ($trackEvent->metaType == 'sequenceNumber') {
                    $metaData = pack('n', $trackEvent->number);
                } elseif ($trackEvent->metaType == 'channelPrefix') {
                    $metaData = pack('C', $trackEvent->channel);
                } elseif ($trackEvent->metaType == 'portPrefix') {
                    $metaData = pack('C', $trackEvent->port);
                } elseif ($trackEvent->metaType == 'endOfTrack') {
                    $metaData = '';
                } elseif ($trackEvent->metaType == 'setTempo') {
                    $tempo = round(60000000 / $trackEvent->tempoBpm);
                    $metaData = pack('N', $tempo);
                    $metaData = substr($metaData, 1); // 3 byte only
                } elseif ($trackEvent->metaType == 'smpteOffset') {
                    $frameRate = $frameRateMapping[$trackEvent->frameRate];
                    $byte = ($frameRate << 5) | $trackEvent->hours;
                    $metaData = '';
                    $metaData .= pack('C', $byte);
                    $metaData .= pack('C', $trackEvent->minutes);
                    $metaData .= pack('C', $trackEvent->seconds);
                    $metaData .= pack('C', $trackEvent->frames);
                    $metaData .= pack('C', $trackEvent->subFrames);
                } elseif ($trackEvent->metaType == 'timeSignature') {
                    $metaData = '';
                    $metaData .= pack('C', $trackEvent->numerator);
                    $metaData .= pack('C', log($trackEvent->denominator, 2));
                    $metaData .= pack('C', $trackEvent->metronomeTimePerClick);
                    $metaData .= pack('C', $trackEvent->count32ndNotesPerQuarter);
                } elseif ($trackEvent->metaType == 'keySignature') {
                    $metaData = '';
                    $metaData .= pack('c', $trackEvent->fifths);
                    $metaData .= pack('C', $trackEvent->mode == 'minor' ? 1 : 0);
                } elseif ($metaEventType >= 0x01 && $metaEventType <= 0x0f) {
                    $metaData = mb_convert_encoding($trackEvent->text, $this->charset, 'utf-8');
                } else {
                    $metaData = $trackEvent->data;
                }

                $packet .= pack('C', $eventType);
                $packet .= pack('C', $metaEventType);
                $packet .= $this->renderVariableLengthValue(strlen($metaData));
                $packet .= $metaData;

                $lastEventType = null;
            } elseif ($eventType >= 0xf0 && $eventType <= 0xfe) {
                $packet .= pack('C', $eventType);
                $packet .= $this->renderVariableLengthValue(strlen($trackEvent->data));
                $packet .= $trackEvent->data;

                $lastEventType = null;
            } else {
                $skipEventType = false;

                if ($this->useRunningStatus && $lastEventType !== null) {
                    if ($eventType === $lastEventType) {
                        $skipEventType = true;
                    }
                }

                if (!$skipEventType) {
                    $packet .= pack('C', $eventType);
                }

                if ($trackEvent->type == 'noteOff') {
                    $packet .= pack('C', $this->mapNoteToMidi($trackEvent->note));
                    $packet .= pack('C', $trackEvent->velocity);
                } elseif ($trackEvent->type == 'noteOn') {
                    $packet .= pack('C', $this->mapNoteToMidi($trackEvent->note));
                    $packet .= pack('C', $trackEvent->velocity);
                } elseif ($trackEvent->type == 'polyKeyPressure') {
                    $packet .= pack('C', $this->mapNoteToMidi($trackEvent->note));
                    $packet .= pack('C', $trackEvent->pressure);
                } elseif ($trackEvent->type == 'controlChange') {
                    if (array_key_exists($trackEvent->control, $controlMapping)) {
                        $control = $controlMapping[$trackEvent->control];
                    } elseif (preg_match('/^id:(\d+)$/', $trackEvent->control, $matches)) {
                        $control = (int) $matches[1];
                    } else {
                        $this->log('Unknown control "' . $trackEvent->control . '"');
                        continue;
                    }

                    $packet .= pack('C', $control);
                    $packet .= pack('C', $trackEvent->value);
                } elseif ($trackEvent->type == 'programChange') {
                    $packet .= pack('C', $trackEvent->number);
                } elseif ($trackEvent->type == 'channelKeyPressure') {
                    $packet .= pack('C', $trackEvent->pressure);
                } elseif ($trackEvent->type == 'pitchBendChange') {
                    $packet .= pack('v', $trackEvent->value);
                }

                $lastEventType = $eventType;
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
