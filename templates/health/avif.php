<?php

/**
 * avif
 *
 * AVIF installation instructions, shown
 * when avifenc is not installed.
 *
 * @package     Takopic
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
	AVIF
	===================== -->
<ul class="takopic-install__list">
	<li>
		<span class="takopic-install__os">Linux</span>
		Dependant on your distro, you may need to build from source.
		<a href="https://aomediacodec.github.io/av1-avif/" target="_blank" rel="noopener">More information</a>
	</li>
	<li>
		<span class="takopic-install__os">Mac</span>
		<code>brew install libavif</code>
		<a href="https://formulae.brew.sh/formula/libavif" target="_blank" rel="noopener">More information</a>
	</li>
	<li>
		<span class="takopic-install__os">Windows</span>
		<a href="https://github.com/AOMediaCodec/libavif/releases" target="_blank" rel="noopener">Download for Windows</a>
	</li>
</ul>
