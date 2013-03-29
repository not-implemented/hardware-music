<?php

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__) . '/midi-editor');

require_once 'MidiFile.php';
require_once 'MidiEditor.php';
require_once 'NoteRenderer.php';
require_once 'BinaryNoteRenderer.php';

$midiPath = __DIR__ . '/midi-files';
$errors = array();

if (isset($_FILES['uploadfile'])) {
    $destFilename = basename($_FILES['uploadfile']['name']);

    if (!preg_match('/\.mid$/', $destFilename)) {
        $errors[] = 'Only MIDI files (*.mid) are allowed!';
    } else {
        move_uploaded_file($_FILES['uploadfile']['tmp_name'], $midiPath . '/' . $destFilename);

        header('Location: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?file=' . urlencode($destFilename));
        exit();
    }
}

$selectedMidiFile = null;

if (isset($_REQUEST['file'])) {
    $file = $_REQUEST['file'];

    $selectedMidiFile = new MidiFile();
    $selectedMidiFile->load($midiPath . '/' . $file);

    $midiEditor = new MidiEditor();
    $midiEditor->analyzeTracks($selectedMidiFile);

    if (isset($_REQUEST['selectTrackChannel'])) {
        list($track, $channel) = explode('/', $_REQUEST['selectTrackChannel']);

        $midiEditor->selectTrack = $track;
        $midiEditor->selectChannel = $channel;
        $midiEditor->modifyProgramType = 40;
        $midiEditor->modifyVelocity = 127;
        $midiEditor->highestNote = 'E6';
        $midiEditor->minimalPause = 100000;
        $midiEditor->modifyTracks($selectedMidiFile);

        $selectedMidiFile->save('output.mid');

        $binaryNoteRenderer = new BinaryNoteRenderer();
        $binaryNoteRenderer->track = $midiEditor->selectTrack;
        $binaryNoteRenderer->channel = $midiEditor->selectChannel;
        $binaryNoteRenderer->save('scanner-output.bin', $selectedMidiFile);
    }

    foreach ($selectedMidiFile->logMessages as $logMessage) {
        $errors[] = $logMessage;
    }
}

$midiFiles = array();
foreach (glob($midiPath . '/*.mid') as $filename) {
    $midiFile = (object) array(
        'filename' => basename($filename),
        'title' => basename($filename, '.mid'),
    );
    $midiFiles[] = $midiFile;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hardware-Music</title>
    <link href="css/hardware-music.css" rel="stylesheet">
</head>
<body>
    <h1>Hardware-Music</h1>

    <?php if (count($errors) > 0): ?>
        <ul class="error">
            <?php foreach ($errors as $error): ?>
                <li><?= $error ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($selectedMidiFile !== null): ?>
        <h2>Current selected MIDI file</h2>
        <ul>
            <?php foreach ($selectedMidiFile->tracks as $trackId => $track): ?>
                <li>
                    Track <?= $trackId . ($track->trackName !== null ? ' (' . $track->trackName . ')' : '') ?><br />

                    <?php if ($track->instrumentName !== null): ?>
                        Instrument name: <?= $track->instrumentName ?><br />
                    <?php endif; ?>
                    <?php if ($track->copyright !== null): ?>
                        Copyright: <?= $track->copyright ?><br />
                    <?php endif; ?>

                    <?php if ($track->programTypes !== null): ?>
                        Used instruments: <?= implode(', ', $track->programTypes) ?><br />
                    <?php endif; ?>

                    <?php if (count($track->noteCountPerChannel) > 0): ?>
                        Note counts<br />
                        <ul>
                            <?php foreach ($track->noteCountPerChannel as $channel => $noteCount): ?>
                                <?php
                                    $trackChannelSelection[$trackId . '/' . $channel] = 'Track ' . $trackId . ' / Channel ' . $channel;
                                ?>
                                <li>Channel <?= $channel . ': ' . $noteCount ?> notes</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
            <input type="hidden" name="file" value="<?= $file ?>" />
            Select Track/Channel:
            <select name="selectTrackChannel">
                <?php foreach ($trackChannelSelection as $trackChannel => $title): ?>
                    <option value="<?= $trackChannel ?>"><?= $title ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Apply" />
            <input type="button" name="play" value="Play" />
            <a href="output.mid">Preview modified MIDI</a>
        </form>
        <hr />
    <?php endif; ?>

    <h2>Upload new MIDI File</h2>
    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" enctype="multipart/form-data">
        Select MIDI file: <input type="file" name="uploadfile">
        <input type="submit" value="Upload" />
    </form>
    <hr />

    <h2>MIDI Files</h2>
    <ul>
        <?php foreach ($midiFiles as $midiFile): ?>
            <li><a href="<?= $_SERVER['PHP_SELF'] ?>?file=<?= urlencode($midiFile->filename) ?>"><?= $midiFile->title ?></a></li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
