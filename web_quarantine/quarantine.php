<?php
// Important note: both mhonarc and swaks must be installed first!

$base_uri = "https://www.example.com/quarantine/?m=";
$pass = "MySecretPasswordSharedWithQuarantineServer";

$delivery_action_sender = "postmaster@example.com";
define("SENDER", "$delivery_action_sender");
$delivery_action_recipient = "$delivery_action_sender";

$upload_dir = "m";

# Messages {{{
$subject_blocked = "Vous avez reçu un message qui a été bloqué";
$message_blocked = "Nos serveurs de messagerie ont bloqué un message qui vous était destiné car il contient probablement un virus ou du spam.\r\n\r\nVous pouvez le consulter et éventuellement forcer sa livraison :\r\n__URI__\r\n\r\nSans action de votre part, il sera supprimé dans 15 jours.";

$subject_delivered = "Votre message bloqué est en cours de livraison";
$message_delivered = "Votre message qui avait été bloqué par nos serveurs de messagerie a été débloqué par vous (ou l'un des autres destinataires) et est maintenant en cours de livraison.";

$subject_dropped = "Votre message bloqué a été supprimé";
$message_dropped = "Votre message qui avait été bloqué par nos serveurs de messagerie a été supprimé par vous (ou l'un des autres destinataires).";

$warning_message = "Un virus ou spam a été détecté dans le message ci-dessous. <b>Si vous êtes certain⋅e qu'il s'agit en fait d'un message inoffensif</b>, vous pouvez engager votre responsabilité en cochant la case «&nbsp;j'ai bien compris les risques&nbsp;» puis cliquer sur le bouton qui apparaîtra. Sinon, supprimez-le. Dans le doute, contactez l'expéditeur directement (indiqué par la ligne <i>From:</i> du message).";
/*}}}*/

# Functions {{{
function notify_recipients($recipients, $message, $subject) {
    $addresses = explode(', ', $recipients);
    $message = wordwrap($message, 70, "\r\n");
    foreach($addresses as $address) {
        mail($address, $subject, $message,
            "From: " . SENDER . "\n"
            . "Content-Type: text/plain; charset=UTF-8"
        );
    }
}

function html_msg($msg, $class) {
?>
    <div class="msg <?php echo $class; ?>">
        <?php echo _($msg)."\n"; ?>
    </div>
<?php
}

function html_success($msg) {
    html_msg($msg, "success");
}

function html_error($msg) {
    html_msg($msg, "error");
}
/*}}}*/

# Before receiving the uploaded mbox file, check authentication (seed + key) {{{
if (isset($_POST["s"]) && isset($_POST["k"]) && $_POST["k"] == sha1($_POST["s"] . $pass)) {

    # Did the upload was OK?
    if (isset($_FILES["f"]) && $_FILES["f"]["error"] > 0) {
        echo _("Error during upload.");

    } else {
        $name = $_FILES["f"]["name"];
        $tmpfile = $_FILES["f"]["tmp_name"];

        $recipients = $_POST["rcpt"];
        $origin_server = $_POST["srv"];

        $mime = mime_content_type($tmpfile);
        $sha = sha1_file($tmpfile);
        $destdir = "$upload_dir/$sha";

        # Note: Some spam may be detected as other than "text/plain"
        # ("text/x-fortran" by example).
        if (substr_compare("text/", $mime, 1, 5)) {
            # We assume that filename is the message ID, minus it's
            # extension.
            $destdir = "$destdir/" . str_ireplace('.eml', '', $name);
            # The final URI used in email to notify recipients.
            $uri = $base_uri . str_replace("$upload_dir/", '', $destdir);

            mkdir($destdir, 0750, true);
            move_uploaded_file($tmpfile, "$destdir/$name");

            # We need to store the origin SMTP server, so we could directly
            # send delivery request.
            $srvFile = fopen("$destdir/origin_server", "w");
            fwrite($srvFile, $origin_server);
            fclose($srvFile);

            # We store the recipients so we could notify them when
            # mail will be delivered or dropped.
            $rcptFile = fopen("$destdir/recipients", "w");
            fwrite($rcptFile, $recipients);
            fclose($rcptFile);

            # Convert mbox file to HTML with pictures and attached files.
            exec("/usr/bin/mhonarc -quiet -single "
                . "-attachmentdir " . escapeshellarg($destdir)
                . " " . escapeshellarg("$destdir/$name")
                . " 2>/dev/null > " . escapeshellarg("$destdir/index.html"),
                $mhonarc_output, $mhonarc_result);

            if ($mhonarc_result == 0) {
                notify_recipients($recipients,
                    str_replace("__URI__", $uri, _($message_blocked)),
                    _($subject_blocked));
                echo $uri;

            } else {
                echo _("Failed to convert to HTML.");
            }

        # Anything else is denied.
        } else {
            echo _("File is not acceptable.");
        }
    }

# Tried to upload a file without being authenticated
} else if (isset($_FILES["f"])) {
    echo _("Failed to authenticate.");
/*}}}*/

# Display HTML {{{
} else {
?>
<html>
<head>
    <meta charset="utf-8" />
    <title><?php echo _("Messages en quarantaine"); ?></title>
    <style>
    body {
        background-color: #FFF;
        color: #000;
    }
    .msg {
        margin: 1em auto;
        padding: 0.5em;
        width: 50%;
        text-align: center;
        border-width: 2px;
        border-style: solid;
        font-weight: bold;
    }
    .error {
        background-color: #FFE4E4;;
        border-color: #D8000C;
        color: #D8000C;
    }
    .success {
        background-color: #DFF2BF;
        border-color: #4F8A10;
        color: #4F8A10;
    }
    iframe {
        width: 90%;
        height: 65%;
        margin: 0 4%;
        border: 5px inset #F00;
        background-color: #FFE4E4;
    }
    div.warning {
        margin: 1em auto;
        width: 80%;
        text-align: justify;
    }
    div.options {
        margin: 0.5em 1em 1em;
        text-align: center;
    }
    label.show, input.show {
        margin-bottom: 2em;
    }
    form#button_deliver {
        display: none;
    }
    input#show:checked ~ form#button_deliver {
        display: inline;
    }
    form {
        display: inline;
        margin: 0 1em;
    }
    button {
        text-indent: 48px;
        height: 54px;
        font-weight: bold;
        background-repeat:no-repeat;
    }
    /*Button base64 images {{{*/
    button.deliver {
        /* 48px-Gnome-mail-forward.svg.png */
        background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAABmJLR0QA/wD/AP+gvaeTAAAAB3RJTUUH2QcWEhMER5fBgQAACO1JREFUaIHtmWuMXVUVx397n3PfvZ3nnffQF0OHTkuBKiaAsUBijJIqGsJLRQ1RCTEm+oFPJvDJkGjUBElQUx6KCYQEKATbIrZAiVOwr6HttIUyM7R0Ou3McGfu67y3H859996ZWyigSf83655z117nzP+/9t5r73MGLuIiLuIiLuIi/o8hqh0P/fahGwKaeNq2nfbPg1A5dF1OO5647f5f3r+jbky1I6hrT913788SmnZOUxUUyj+UzgGUosxd1Vb8Kn2rSk/5b9M0E5sf+/NTQE/DAizL6tJ1Hdf1KsmqPBWlUEqhCj6V95H3V7T7bZ6qvq5+bLm/va0Dx3G7FkpjzTQrBUKURpcCBAqUQImSU/hf5Yn1IQrthWvzzaIqrkZsuV+p6uBzIWs5PeVhWRZCCN8QIARC+H+oYOfOoJLvnKYasYUkieoAIXBt7+MLkEKSy2UxDKPIthFClU2VhBq6MN/mOQ45M7dAXBnXeg3xeJxUOkU2k2noRudi8ezVguu6pDJZwuFQQ/F1BQC0tbYxPTODkcvlJ3EVv1ocq+OgWJlKFxbcVX6lmJ6dIRaLNEQeFhDg81P09vQyPj6GaZn5alOfewWXWjdcIFagM/HBBK0tLefVefUF5EsZwKpVl3Lo0EFsxymWO1+eqoirRaxCbB1iAS3AkaOH6ezs8gsGjVWgBQSU12MPqWlceukA+/btRXlecQ0oJ1Re04u0y0gUw6t4hUMRDowcoLu7C13Xij3fKGoKUEVCfilTnkcsFmXF8hUMD/+bfJGu3QOqOuulHivvNYEgGomx+61hOjs7iESjlSvyJxHgJ88n7+VNeYrmlhZ6+/p5Y9frSKnV7IFi5qvnS1msEIJoNMauXW/Q2tbK0qYmqmR/QgGFzHqFXvDwlAcoOjs7SLQl2LHznwghS+QL+SvvgYr54p/qWoB4bCk7X9vBkvgSEomEP+orRl6dedWogMIexysMoTLTNI3evl7m5+d57fWdhEJhwuEIAS2AFDK/J6jsASk1QsEgS+NNRMIRtrz4PMnkR/T0dKNpGlUDrCHiBdTZcvqZB+FnQlQ0ceToKDfd9FWSyVkef2Iz69etZ2DgMqLRGLquI2VlXizbIpVKsXfvMAcOHeS66zbS19XOyMH9DA6upnr4nI+Q2ps5wFOqxLvsXocPH2LwsssJR8K06wk2bdrEsaPH2P7qNmZnZ3EsG9uxsSyLYDBIQNfRAwG8UCuX9HTzlRu/xuSsQa+AtWvWceToKCtXrazo5bolq1EBFMa/UBXJn5iYYPnyFUSiEWzLAhSBQJChoSHWDK3JD/faW+s3DkzRc0kbSsDp5Bzz6QzNS6IMrLqMEx9O0N3dnc9V+WdxLFBGK6vQ1NQU7e0JorEotmPnJ7YqO9axfFWamrPImC6ukqSyDjPzBoaVQwsIerp7mZmZKWZ//3tz/vNIAwrqrsRevv4r5TE3lyQajRKNRXEcG+WVyPuVqrTo1Zr4Silm502yhsL1BLquMzGVQSkwLRNNl7Q0t5JOpVBK8ejL4/zxxQlM2/mYAsoym06nkUIjFovhOg7KUyXyStURcq7lLIdUzsF0oaMtxtvHksWM246NDEgikRi5XBaA5qalPPjEHly10PZ7kb2QaRp4rkc0FsFxXbxihgsrdLmQUuaL52VzoT0eZHImSyrr0NES5+R0jqzhFGNcx0HTJQEtCEqxccMKOtuW8uZkh9j4g+eaz0tAzsiRy+bIZnMEAgEMw8A0DUzTrDAj76s+mqblHw0Ly/Qt0aQzOZ0CITk7b3LNuj7+/q8PME0b2/LNNMyKNeHK1X1cNTRALpx7+cs/fCaxmIAgEAcwjBxZI4OQkDNzefK+GWVmWiamlSdtmVjVZpesrzXAxGQSXZeMnkxz+bIEc4bkkZfGGDudImua2K6N5Zj5IQD7xmdZN9DF168f6DdD9rb1P32it1qAjr9MRYEIEJWanE6l0olwKFqv18oHWs2ftZ4FlnWGmc+OY1oeUgpGP0zzjetXMzo+zXO7pzg1nca0/EkbCupIKTAtl/3jc94VK9uDAU12bXltdOsV9zy5aeQv3x8r3LrwfB4piLj7R3ffMLRm8PdKqbrj7uPi7TPt4povXkUoFOLkTJaALulvj9AaCxEOSnRZmrDvn8lw4mzOczyR0nTdumpls5o8m3affeVQ0racW/6z+XtHCwIK0IEw/lAKAFq+fcHHzvPB4C0P3rjhC1964JaNg9Hdx2b9PZxauMwIIRRCKF0Tav3yFjWTTKmnXj44b7rOrXv+9N13Fq5RC79COG9s+PGjuq7F9/3ijmvbTn6UFdMpqySgTIhCCYEojUThn2uaVGv7m0hnTO+xF/ZlLOXepV1Igothcs9LqmP9zamc5V53/RW9wek5Q6BACkSFgdCkKPmlQBP+26nplMXVq9oIh/TgkbHp2z9TAYB2et/wu/rya29ub13Sum5Zm0xmLOF6nsi/5BJCIIUQhXdqSCn8d2pSoGuStf1NnElmeGb7oYztWrd91gIkZLRgOPb6RCr+zSWxUOia1Z0yHJBC4RNVCiF9FWhCIqV/HtQla/tbmE2m2PzC3vTc7Kk7R/56357PWgCAljwxYmhSbjtlNF89cvxsc0dTTPYnonJ5xxJWdcfVskQUTZMqk3PRhCAQ0NTaS5rVqbNz6rEt++dnT4zeOfr8rw4CzgWdpI0KAEL41S644qafr2/pH7xdC4SvxJMJT4hQOKiLX997I8PvzhDQJJf3x9W7H8x4f9v6zuyZ8f13jG39zXuACbiL/RPg04ALGOSfm8Ze/cPeMRjBL+MSkBvuefItTRdEwzqDPXEOHD/jPL1tZPrM+Fu3jW9/eCJP3oO6j5SfOjwgB9h5Djp+z0j89+BKk5KhviZ2Hz7lPLfj8IfGife/M7794dNAxR778xJQgEOJkCwzpUnBrpET1ou7jo1Fzdi3dv/jgWStG3zeAsrh5Q2EUFvePG6/svv44ahM3rrz8bvS9S66YNuEC41Xht/fI8Knv73zkfvqkof/rR4owpPyJ7Y3sHXP7+6wF4v9L+sYrmVSs/TlAAAAAElFTkSuQmCC);
    }
    button.drop {
        /* Gnome-edit-delete.svg.png */
        background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAABmJLR0QA/wD/AP+gvaeTAAAKWUlEQVRoge2Z229c13XGf3uf25w5Z2YoypRkUhKpiymqTutUbuOkQOMibVMDDhpHrWI3CILGCewCdVugKPrYqn9EXwsEdQrFjt0ihR/iuH6I7SSGq5slkZSsOLREkRTF4YjkaOZc9qUPZ0iRMilSIpP0wQvYODOHZ876vr2+tfbei/CJfWKbMvHLeOnx48fl8PD7jxs4IoSwVohTnxr61I+PHz9uttrXlhN4+umvPGKQJwYG9g0NDg6SJAkfXLrI5NTUsMQ8/b3v/ef5rfS3pQSeeebLe5Clk9969ts9j3/+cZRWzM7WaTTqvPvuz3jjf96cxWSPnjjxX2Nb5XNTBI4dOxZbaX/fkeKRMCwfSZPkT5544onqV546SqkUriAw26jz9jvvcPb9c3VXuj/I8+yUxP6vMc7Zl19+uf0rI3D060cfdLX8WhAEz3Z3bXvoyJFHOXjwoFet1rDWsGvnTiqVLgCklACkacr1G5MMD59DSBcpBDM3ZvTo6Eg+9tEVTzrOGa3y7+SO+f6rL746+UshcOzYsb1+yfuXAwf2/8WXnvySt3dPv/QDnyzLSNKELE3QWtG/9wCzjVle/9EPmZ6eBizbu7fzx3/0RYKSz8nT71KrbsNxXVzXBQM36jOMjoyokydPKYt90ar2P5848YOJLSNw7JmjTz50YPCl5597vrx9ew9ZnpKkCWmSkOc5t2412bVrF6UgRCnDv7/4HdIsRakcbYrC4zoO3/j6XzI1PcHI6AhJu83AvgGklLiuh+/7aG04f+6CffvtHzdVpo699NIrP1wPm7PeA1/96tHPP/bZz732D3//j6WwXCZNC9CmA+zq+Dj79x2gHEbEccwbb76OUgoArRQgCEslPD9g6vokX/iDP6Svr4/evj4ujo4SxzHGaPI8wxhNb1+vODw0FFy8dPHPBh869Obw8PD43fC56xHwAv9fn/vW8750JCpTONLBuhYhBAgY6B+g0WjQbreQjkM5DOnp2UGWJmQqxygNWJASRwpOnzmN1grHddi5a+cKX8YY2u0W5ajMU099OTxx4qV/Aw5vhoDIc3U4CAIEAtfpBEyAQCCEoBSEuJ6LF7ikScoDPT1kWUaWZiido7XBGI3FgrU0b80Vv8+K369mWZbR3d2NtXZwvQleNwLWWqc+O8OOnp0gXBCikzgCNEgp6N62HWsMSZrQbrcKAnlGnmdordHGYLVBG42xBq00aZqgtPr4jHVITU1eB5CbJgDQarV45ydv8RuHH6ara9uiJwSgtWZhYQFHCqR0qMRVLBajDUrnZHmOynPSrEj8pN0iM6aIghBYW8jRdV2klLTbCWO/GAO7EWQbICCEMFpr2W63OH32FNu7t9Pbu5tKpUIQBCitkUqR5/lSKVVao5XqfDZonaOURndmPPB9At9fMQnzCwtMX7/OzEwdrRTlKAJYd++0HgHrus6HY2NjB3se2EG9UWdhYZ4PP7xMWA7ZVuuiWq1RKoWUwxAhBMbYQipGo5XuSKi4GmNQWpGlKc1mk5tzN2ncbDA/N0eapOR5DkAURUzP3EBKcWnTEchT883v/sd333rhr1+gr3c3jUYdAKUUc/NztNptPM8jCAJKpRIgCsCqiELekU+apSTtpCOjhCwrAGfLSrKUDrVaxML8HKMjo+icZ9fDt+46sGPHjgk/8P7pwoXzhKWQoaEhymEZIQVYi5QOjlMMIQRKKdI0IUkS2kl7aaRpSpZlKKU60SgiYqzFcz3CMMRzfS5evMjo6AhZnnNjeuavxsbG7iqjDSUxwN/+zd/xyqvf50dvvM7nPvt7fPq3P82evj0gWEpER0oUGiFWlkghRGfFLRLVdTzCsAQWcq0ZH7/Kzy9f5srVKyhlePTRI7z33nsbwrVhApW4wmce+ww7enq4NnGN98+dZX5+nv379nNo6DB79+xhe3c3UVyhHEYY36C1RilFrnLyPGN+foHGzQaz9ToTExNcvXaVG9M3cB2XcrnMvoF9fHD553iet1FYGyfgOA5hKSSuxnRvf4AoivBcjwsj5zl//hxnzpwiSVNazVu0kzbWGpZvtay1CCnxXA/f84gqEVEUsVBaII4rVOKYUlhaitKWEwAQUhAEAXFUoVarUYljkrTFnj5FuVymHIW4rsfMzDSXLn1AFMXElZi4HDHbqDN8YYRyHFGtVKnWKkX5nJunXC4ThiG+76+5Om+egGBpcRGiIFKtVOna1oXVUK3VqFRifD8gKHnMzc1TiStUa1UqlSp+4DE5OUVciTsEqiRJmyAIcF33noEv2sZjtRoneduplBLf9wnDkCiKimscFRGII8JyMcOu6+I46xa/Ddt9EVicrfuZtfud6bXsviOwWCrFPZxK7yytW2Gbk1DnTLDhZ1f5vFn7lUhoueY3I7/VbPMRWGbWrr4HduTKpP21ReBOGRTfi3trgYfbEdjqBIZ7JHBn+RNCfiwFVltF/99IaBHcnSCWz/7dCPzaIyBX07K4+zOwWuTWJnIvGzm4BwKCYru8HMRqQJxVI3B7x7KehEqlcKOQgHuNgLO6hFY8swEJrfa7xXthWLoXSOsTuHz58lJMrVlZaZZXoaUXrkJguazWIhGGxcyXwtsRWO57LbsbAQFEjUajtnij2bq1AogUEu4on2vlwJ2AAz9Y8a5F6ZRK/tL9ju+Iu6z3axEQQBmIlFLR4s3x8StA0QIsWirqY80puYo8Fjt6y6vYYrJ6fnEe9jvfl0ew4zvqYFmVxFrnAb8zPGutBxhttFx02k5azM01qNdvFC/xXKIoolKpkmZpca/TPg+CUtHoMoa4EuN0jo+Lh4v+/r1obWklrSVynY627vj2OlgUkN4JdD0JySzLLNaeO3v2jPnNh3+LMAyx1lCfrdNqd5xKyLKUZnOBj8bHANi5ayfd3d3s3LGLsY9+AcDu3X3UqlWkI5ltzC4RlVJgjMZxHGq1LiYnJrU25myWZbaDcU0J3e1k4Xb+7iZ5+t7sbP1PK3HF+93feUzWqjXotFCklDiOg7aaNEuRUnRahQ65zpltzNBsLhQtFGMAW/zfoNPoMsbg+wFROcILfC6NXsqvXP1o7szJk881GnMNIOnMfMoqDce1CFiKtp4AaNQbtxr1m/99c+Hmg+/+7KcDFuz+gf1y/76D1Ko1yuWIIAjwPK9I2MXmVgfkYoNQSgfXLZpg5TAkimPiqMKthSYjI6P56dOnzdT166+989ZPXxgfn5gAWkC7Mz7eCb5baDomgXDZKPUf6N8zNHjoz7u21Z50HffBoUND+eChQb+3t8+t1WpUq1VKQQlrDdoYlFJkWUaapszPzTHbmKXRaDA1NaUmrk2kU9enAq301Ztzc6+NXBx9ZXxsfGIZ6MWxZnNro5sTCZSAYNk16D/Q37e798FHoqjycLUSH3I8b5cUossYXbEW11rrAAghlJBCCUtTW9NQWT7ZbN4aabaaI1fHr50ZHxuf4rZMkmXXdZu797O7EtyuUj5FrnjczhmnQ3h58i1K0gC6MxSQd67ZsrHBxvr9E1jL1gK/aKuR+MT+D7FhuC+sBkpsAAAAAElFTkSuQmCC);
    }
    /*}}}*/
    </style>
</head>
<body>
<?php
/*}}}*/

# User required to see an email
if (isset($_GET["m"])) {/*{{{*/

    # But it does not exists
    if (!file_exists("$upload_dir/" . $_GET["m"] . "/index.html")) {
        html_error("Ce message n'existe pas ou plus. Peut-être a-t-il déjà été
            traité par vous ou l'un de ses autres destinataires ?");

    # The email exists, and we display it, and deliver or drop it options
    } else {
        # Format of "m": "<sha1>/<msgid>"
        $m_array = explode('/', $_GET["m"]);
        $sha = $m_array[0];
        $msgid = $m_array[1];
        $basedir = "$upload_dir/$sha/$msgid";/*}}}*/

        # We got an instruction (deliver or drop) {{{
        if (isset($_POST["action"])) {
            $action = $_POST["action"];

            # We only accept two actions:
            if ($action == "deliver" or $action == "drop") {
                $srvFile_path = "$basedir/origin_server";
                $rcptFile_path = "$basedir/recipients";

                if (file_exists($srvFile_path) and file_exists($rcptFile_path)) {
                    $origin_server = file_get_contents($srvFile_path);
                    $recipients = file_get_contents($rcptFile_path);

                    # Send request by mail to the origin SMTP server
                    # TODO: It must be possible to do the same thing (specify
                    # wich SMTP server to send mail to) purely in PHP, but I
                    # don't know. So I use swaks.
                    exec("/usr/bin/swaks -n -f $delivery_action_sender"
                        . " -t $delivery_action_recipient"
                        . " -s '$origin_server'"
                        . " --h-Subject '$action $msgid'"
                        . " --body /dev/null",
                        $action_output, $action_result);

                    if ($action_result == 0) {

                        # Delete local copy
                        $rmfiles = array_diff(scandir($basedir), array('.', '..'));
                        foreach ($rmfiles as $rmfile) {
                            # This works because we don't have folders here.
                            # Otherwise we would have to recursively loop
                            # inside.
                            unlink("$basedir/$rmfile");
                        }
                        rmdir("$upload_dir/$sha/$msgid");
                        rmdir("$upload_dir/$sha");

                        # Notify all recipients about this action
                        if ($action == "deliver") {
                            notify_recipients($recipients,
                                _($message_delivered), _($subject_delivered));
                            html_success($subject_delivered);
                        } else if ($action == "drop") {
                            notify_recipients($recipients,
                                _($message_dropped), _($subject_dropped));
                            html_success($subject_dropped);
                        }
                    } else {
                        html_error("La requête a échouée. Réessayez plus tard.");
                    }
                } else {
                    html_error("Erreur. Fichiers système manquants.");
                }

            }
        /*}}}*/

        # Form options to deliver or drop the message {{{
        } else {
?>
        <div class="warning">
            <?php echo _($warning_message); ?>
        </div>
        <div class="options">
            <label class="show" for="show">
                <?php echo _("J'ai bien compris les risques&nbsp;:"); ?></label>
            <input class="show" type="checkbox" id="show" name="show"/>
            <br/>
            <form method="post" id="button_deliver">
                <input type="hidden" name="action" value="deliver" />
                <button class="deliver" type="submit">
                    <?php echo _("Recevoir normalement"); ?></button>
            </form>
            <form method="post">
                <input type="hidden" name="action" value="drop" />
                <button class="drop" type="submit">
                    <?php echo _("Supprimer"); ?></button>
            </form>
        </div>
        <iframe sandbox src="<?php echo "$basedir/index.html"; ?>"></iframe>
<?php
        }
        /*}}}*/
    }

# Default HTML page {{{
} else {
?>
    <h1><?php echo _("Messages en quarantaine"); ?></h1>
    <p>
    <?php echo _("Les messages que nos antivirus détectent comme virus ou spam sont
    mis en quarantaine pendant 15&nbsp;jours, afin de vous laisser la possibilité de les
    consulter, voir de forcer leur livraison normale.")."\n"; ?>
    </p>
<?php
}
?>
</body>
</html>
<?php
/*}}}*/
}
// vim: foldmethod=marker
