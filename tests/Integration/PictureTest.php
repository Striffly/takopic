<?php

/**
 * PictureTest
 *
 * The <picture> elements offering the AVIF and WebP
 * versions of the images to the browsers.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @repo        https://github.com/Striffly/takopic
 *
 */

namespace Takopic\Tests\Integration;

use Takopic\Delivery\Picture;
use Takopic\Tests\TestCase;

class PictureTest extends TestCase
{
	/**
	 * The URL of the uploads folder.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Makes the files of an image: photo.jpg with its
	 * WebP and AVIF versions, and a thumbnail with its
	 * WebP version only (its AVIF was heavier).
	 */
	public function set_up()
	{
		parent::set_up();
		$this->url = wp_get_upload_dir()['baseurl'];
		wp_mkdir_p($this->uploads . '/2026/10');
		wp_mkdir_p($this->uploads . '/takopic/formats/2026/10');
		foreach (['2026/10/photo.jpg', 'takopic/formats/2026/10/photo.jpg.webp', 'takopic/formats/2026/10/photo.jpg.avif', '2026/10/photo-300x200.jpg', 'takopic/formats/2026/10/photo-300x200.jpg.webp', '2026/10/other.jpg'] as $file) {
			touch($this->uploads . '/' . $file);
		}
	}

	public function test_the_image_is_wrapped_with_its_avif_and_webp_versions()
	{
		$html = Picture::render($this->img());

		$this->assertSame(
			'<picture class="takopic-picture">'
			. '<source type="image/avif" srcset="' . $this->url . '/takopic/formats/2026/10/photo.jpg.avif 640w, ' . $this->url . '/2026/10/photo-300x200.jpg 300w" sizes="(max-width: 640px) 100vw, 640px">'
			. '<source type="image/webp" srcset="' . $this->url . '/takopic/formats/2026/10/photo.jpg.webp 640w, ' . $this->url . '/takopic/formats/2026/10/photo-300x200.jpg.webp 300w" sizes="(max-width: 640px) 100vw, 640px">'
			. '<img data-takopic src="' . $this->url . '/2026/10/photo.jpg" srcset="' . $this->url . '/2026/10/photo.jpg 640w, ' . $this->url . '/2026/10/photo-300x200.jpg 300w" sizes="(max-width: 640px) 100vw, 640px" alt="A photo">'
			. '</picture>',
			$html
		);
	}

	public function test_an_image_is_wrapped_once()
	{
		$html = Picture::render($this->img());

		$this->assertSame($html, Picture::render($html));
	}

	public function test_the_text_around_the_image_is_kept()
	{
		$html = Picture::render('<figure>' . $this->img() . '<figcaption>Caption</figcaption></figure>');

		$this->assertStringStartsWith('<figure><picture class="takopic-picture"><source', $html);
		$this->assertStringEndsWith('</picture><figcaption>Caption</figcaption></figure>', $html);
	}

	public function test_an_image_without_versions_is_not_wrapped()
	{
		$img = '<img src="' . $this->url . '/2026/10/other.jpg" alt="">';

		// Marked as done, so that it is not looked up again.
		$this->assertSame('<img data-takopic src="' . $this->url . '/2026/10/other.jpg" alt="">', Picture::render($img));
		$this->assertSame('<picture class="takopic-picture hero"><img data-takopic src="' . $this->url . '/2026/10/other.jpg" alt=""></picture>', Picture::render($img, 'hero'), 'Unless the <picture> is asked for, with its class.');
	}

	public function test_a_file_added_next_to_the_image_is_not_offered()
	{
		// E.g. uploaded to the media library under this name, by any author.
		touch($this->uploads . '/2026/10/other.jpg.avif');
		$img = '<img src="' . $this->url . '/2026/10/other.jpg" alt="">';

		$this->assertSame('<img data-takopic src="' . $this->url . '/2026/10/other.jpg" alt="">', Picture::render($img));
	}

	public function test_the_files_of_takopic_are_left_alone()
	{
		$img = '<img src="' . $this->url . '/takopic/formats/2026/10/photo.jpg.webp" alt="">';

		$this->assertSame($img, Picture::render($img));
	}

	public function test_the_images_out_of_the_uploads_folder_are_left_alone()
	{
		$images = [
			'<img src="https://cdn.example.com/2026/10/photo.jpg" alt="">',
			'<img src="' . home_url('/wp-content/themes/theme/photo.jpg') . '" alt="">',
			'<img src="' . $this->url . '/2026/10/../10/photo.jpg" alt="">',
			'<p>No image</p>',
		];
		foreach ($images as $img) {
			$this->assertSame($img, Picture::render($img));
		}
	}

	public function test_the_query_string_and_the_scheme_do_not_matter()
	{
		$src = preg_replace('#^https?:#', 'https:', $this->url) . '/2026/10/photo.jpg?ver=2';
		$html = Picture::render('<img src="' . $src . '" alt="">');

		$this->assertStringContainsString('<source type="image/avif" srcset="' . preg_replace('#^https?:#', 'https:', $this->url) . '/takopic/formats/2026/10/photo.jpg.avif">', $html);
	}

	public function test_the_images_of_the_site_are_wrapped_when_enabled()
	{
		$id = $this->attachment();

		$this->assertStringNotContainsString('<picture', wp_get_attachment_image($id, 'full'));
		$this->assertStringNotContainsString('<picture', apply_filters('the_content', $this->img()));

		self::set('serve_picture', true);
		$this->assertStringStartsWith('<picture class="takopic-picture">', wp_get_attachment_image($id, 'full'));
		$this->assertStringContainsString('<picture class="takopic-picture">', apply_filters('the_content', '<p>' . $this->img(' class="wp-image-' . $id . '"') . '</p>'));
	}

	public function test_the_images_of_the_admin_are_not_wrapped()
	{
		$id = $this->attachment();
		self::set('serve_picture', true);
		set_current_screen('upload');

		$this->assertStringNotContainsString('<picture', wp_get_attachment_image($id, 'full'));
	}

	public function test_takopic_image_always_offers_the_versions()
	{
		$id = $this->attachment();

		$html = takopic_image($id, 'hero');
		$this->assertStringStartsWith('<picture class="takopic-picture hero"><source type="image/avif"', $html);

		$lazy = takopic_image($id, '', true);
		$this->assertStringContainsString(' data-srcset="', $lazy);
		$this->assertStringContainsString(' data-src="', $lazy);
		$this->assertDoesNotMatchRegularExpression('/\s(src|srcset)=/', $lazy);
	}

	public function test_takopic_image_sources_returns_the_srcset_of_each_format()
	{
		$srcset = $this->url . '/2026/10/photo.jpg 640w, ' . $this->url . '/2026/10/photo-300x200.jpg 300w';

		$this->assertSame(
			[
				'image/avif' => $this->url . '/takopic/formats/2026/10/photo.jpg.avif 640w, ' . $this->url . '/2026/10/photo-300x200.jpg 300w',
				'image/webp' => $this->url . '/takopic/formats/2026/10/photo.jpg.webp 640w, ' . $this->url . '/takopic/formats/2026/10/photo-300x200.jpg.webp 300w',
			],
			takopic_image_sources($srcset)
		);
	}

	public function test_takopic_image_sources_takes_a_single_url()
	{
		$this->assertSame(['image/webp' => $this->url . '/takopic/formats/2026/10/photo-300x200.jpg.webp'], takopic_image_sources($this->url . '/2026/10/photo-300x200.jpg'));
		$this->assertSame([], takopic_image_sources($this->url . '/2026/10/other.jpg'), 'An image without versions has no source.');
		$this->assertSame([], takopic_image_sources('https://cdn.example.com/2026/10/photo.jpg'), 'Nor an image out of the uploads folder.');
		$this->assertSame([], takopic_image_sources(''));
	}

	/**
	 * Returns the <img> of the photo, as WordPress
	 * writes it.
	 *
	 * @param string $attributes
	 * @return string
	 */
	private function img($attributes = '')
	{
		return '<img' . $attributes . ' src="' . $this->url . '/2026/10/photo.jpg" srcset="' . $this->url . '/2026/10/photo.jpg 640w, ' . $this->url . '/2026/10/photo-300x200.jpg 300w" sizes="(max-width: 640px) 100vw, 640px" alt="A photo">';
	}

	/**
	 * Returns an attachment of the photo, with its
	 * thumbnail.
	 *
	 * @return int
	 */
	private function attachment()
	{
		$id = self::factory()->attachment->create_object(['file' => '2026/10/photo.jpg', 'post_mime_type' => 'image/jpeg']);
		wp_update_attachment_metadata($id, [
			'width' => 640,
			'height' => 427,
			'file' => '2026/10/photo.jpg',
			'sizes' => ['medium' => ['file' => 'photo-300x200.jpg', 'width' => 300, 'height' => 200, 'mime-type' => 'image/jpeg']],
		]);
		return $id;
	}
}
