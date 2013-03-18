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

        $this->tracks = array();

        foreach ($chunks as $chunk) {
            $this->tracks[] = $this->parseTrackChunk($chunk);
        }
    }

    public function save($filename) {
        file_put_contents($filename, print_r($this->tracks, true));
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
            $trackEvent->type = null;

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
                $trackEvent->metaType = $this->parseByte($chunk, $offset);
                $metaLength = $this->parseVariableLengthValue($chunk, $offset);
                $trackEvent->data = substr($chunk, $offset, $metaLength);
                $offset += $metaLength;
            } elseif ($eventType == 0xf0 || $eventType == 0xf7) {
                $trackEvent->type = $eventType == 0xf0 ? 'sysEx' : 'sysExDivided';
                $dataLength = $this->parseVariableLengthValue($chunk, $offset);
                $trackEvent->data = substr($chunk, $offset, $dataLength);
                $offset += $dataLength;
            } else {
                $trackEvent->channel = $eventType & 0x0f;
                $eventType = ($eventType & 0xf0) >> 4;

                if ($eventType == 0x8) {
                    $trackEvent->type = 'noteOff';
                    $trackEvent->note = $this->parseByte($chunk, $offset);
                    $trackEvent->velocity = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0x9) {
                    $trackEvent->type = 'noteOn';
                    $trackEvent->note = $this->parseByte($chunk, $offset);
                    $trackEvent->velocity = $this->parseByte($chunk, $offset);

                    if ($trackEvent->velocity == 0) {
                        $trackEvent->type = 'noteOff';
                    }
                } elseif ($eventType == 0xa) {
                    $trackEvent->type = 'noteAftertouch';
                    $trackEvent->note = $this->parseByte($chunk, $offset);
                    $trackEvent->aftertouch = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0xb) {
                    $trackEvent->type = 'controller';
                    $trackEvent->controller = $this->parseByte($chunk, $offset);
                    $trackEvent->value = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0xc) {
                    $trackEvent->type = 'programChange';
                    $trackEvent->program = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0xd) {
                    $trackEvent->type = 'channelAftertouch';
                    $trackEvent->aftertouch = $this->parseByte($chunk, $offset);
                } elseif ($eventType == 0xe) {
                    $trackEvent->type = 'pitchBend';
                    $value1 = $this->parseByte($chunk, $offset);
                    $value2 = $this->parseByte($chunk, $offset);
                    $trackEvent->pitch = ($value2 << 8) | $value1;
                } else {
                    $this->log('Ignored unknown event type "' . $eventType . '"');
                }
            }

            $trackEvents[] = $trackEvent;
        }

        return $trackEvents;
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
