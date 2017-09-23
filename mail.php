<?php

class mail
{
	// Map of mail headers. Most common are "To", "From", "Subject",
	// "BCC".
	private $headers = array();

	// MIME parts, arrays with "headers" and "body" keys.
	private $parts = array();

	function __construct()
	{
		$this->headers = array(
			'Date' => date('r'),
			'MIME-Version' => '1.0',
			'From' => "noreply@$_SERVER[HTTP_HOST]"
		);
	}

	function set_subject($text)
	{
		$this->headers['Subject'] = $text;
	}

	function set_header($name, $value)
	{
		$this->headers[$name] = $value;
	}

	function get_header($name)
	{
		if (isset($this->headers[$name])) {
			return $this->headers[$name];
		}
		return null;
	}

	function set_text($src, $mime_type = null)
	{
		if (!$mime_type) {
			$mime_type = 'text/plain; charset="UTF-8"';
		}
		else {
			if (!strpos($mime_type, 'charset')) {
				$mime_type .= '; charset="UTF-8';
			}
		}

		$headers = array(
			'Content-Type' => $mime_type
			//'Content-Transfer-Encoding' => 'base64'
		);

		//$body = chunk_split( base64_encode( $src ) );
		$body = $src;

		$this->parts[] = array(
			'headers' => $headers,
			'body' => $body
		);
	}

	function attach($src, $filename = null, $mime_type = null)
	{
		if (!$mime_type) {
			$mime_type = 'application/octet-stream';
		}

		$headers = array(
			'Content-Type' => $mime_type,
			'Content-Disposition' => 'attachment',
			'Content-Transfer-Encoding' => 'base64'
		);

		if ($filename) {
			$filename = $filename;
			$headers['Content-Disposition'] .= '; filename="'.$filename
				. '"';
		}

		$body = chunk_split(base64_encode($src));

		$this->parts[] = array(
			'headers' => $headers,
			'body' => $body
		);
	}

	function __toString()
	{
		$s = '';
		$headers = $this->headers;
		if (count($this->parts) == 1) {
			// If there is only one part (plain text assumed), add its
			// headers to the mail headers.
			$headers = array_merge($headers, $this->parts[0]['headers']);

			// Write down all the headers.
			foreach ($headers as $name => $value) {
				$s .= "$name: $value\r\n";
			}

			// Add blank line before the body.
			$s .= "\r\n";

			$s .= $this->parts[0]['body'];
		}
		else {
			$boundary = '===='.uniqid().'====';
			$headers['Content-Type'] = "multipart/mixed; boundary=\"$boundary\"";

			// Write down all the headers.
			foreach ($headers as $name => $value) {
				$s .= "$name: $value\r\n";
			}

			// Add blank line before the body.
			$s .= "\r\n";

			foreach ($this->parts as $part) {
				$s .= "--$boundary\r\n";

				$h = $part['headers'];
				foreach ($h as $name => $value) {
					$s .= "$name: $value\r\n";
				}
				$s .= "\r\n";
				$s .= $part['body'];
				$s .= "\r\n";
			}

			$s .= "--$boundary--";
		}

		return $s;
	}

	function send($to)
	{
		$subj = isset($this->headers['Subject']) ? $this->headers['Subject'] : '';

		//h3::log("Mail: $to ($subj)");

		if (getenv('DEBUG')) {
			$path = uniqid().'.msg';
			file_put_contents($path, $this->__toString());
			return true;
		}

		/*
		 * PHP's mail accepts Subject and To arguments. It will add them
		 * as headers even if they are already present in the headers
		 * argument. So we have to unset those headers temporarily to
		 * compose a MIME source without them.
		 */
		$subj = $hto = null;
		if (isset($this->headers['Subject'])) {
			$subj = $this->headers['Subject'];
			unset($this->headers['Subject']);
		}
		/*
		 * The "to" MIME header is technically different from the $to
		 * argument of the mail function, but PHP treats them as the
		 * same thing. This means, we can't really use the To header.
		 * We have to ignore it.
		 */
		if (isset($this->headers['To'])) {
			$hto = $this->headers['To'];
			unset($this->headers['To']);
		}

		$mail = $this->__toString();

		/*
		 * Put the headers back
		 */
		if ($subj) $this->headers['Subject'] = $subj;
		if ($hto) $this->headers['To'] = $hto;

		/*
		 * Get headers and body as two separate strings
		 */
		$pos = strpos($mail, "\r\n\r\n");
		$headers = substr($mail, 0, $pos);
		$body = trim(substr($mail, $pos));

		/*
		 * Send the mail
		 */
		return mail($to, $subj, $body, $headers);
	}
}

?>
