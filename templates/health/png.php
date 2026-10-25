<?php

/**
 * png
 *
 * PNG installation instructions, shown
 * when optipng is not installed.
 *
 * @package     Takopic
 * @version     0.1.4
 * @author      Clément Gayot
 * @author      Ainsley Clark (Squidge, original author)
 * @category    Templates
 * @repo        https://github.com/Striffly/squidge
 *
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

?>

<!-- =====================
	PNG
	===================== -->
<ul class="takopic-install__list">
	<li>
		<span class="takopic-install__os">Linux</span>
		<code>sudo apt-get install optipng</code>
		<a href="https://zoomadmin.com/HowToInstall/UbuntuPackage/optipng" target="_blank" rel="noopener">More information</a>
	</li>
	<li>
		<span class="takopic-install__os">Mac</span>
		<code>brew install optipng</code>
		<a href="https://formulae.brew.sh/formula/optipng" target="_blank" rel="noopener">More information</a>
	</li>
	<li>
		<span class="takopic-install__os">Windows</span>
		<a href="http://optipng.sourceforge.net/" target="_blank" rel="noopener">Download for Windows</a>
	</li>
</ul>
