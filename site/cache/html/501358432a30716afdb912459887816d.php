<?php return array (
  'meta' => 
  array (
    'title' => 'On Ultralight PHP',
  ),
  'html' => '<h1>On Ultralight PHP 2</h1><p>Cached markdown-to-HTML is essentially free at runtime. One <code>file_exists</code> check, one <code>readfile</code>. That\'s it.</p><pre>The first request to any URL parses the markdown and writes a PHP cache file. Every subsequent request returns the cached HTML until the source .md file\'s mtime changes.<br></pre><p><br></p><p>asdadasd<br></p><p><br></p>
',
);