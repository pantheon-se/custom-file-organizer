<?php

use function WP_CLI\Utils\get_flag_value;

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class Custom_File_Organizer_CLI {

	/**
	 * Scans directories in wp-content/uploads and lists directories with more than 50,000 files.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * @when after_wp_load
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function scan( $args, $assoc_args ) {
		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );
		$this->scan_directory( $base_dir, $assoc_args );
	}

	/**
	 * @param $dir
	 * @param $assoc_args
	 *
	 * @return void
	 */
	private function scan_directory( $dir, $assoc_args ) {
		$iterator = new DirectoryIterator( $dir );
		$files    = 0;

		foreach ( $iterator as $file_info ) {
			if ( $file_info->isFile() ) {
				++$files;
			} elseif ( ! $file_info->isDot() && $file_info->isDir() ) {
				$subdirectory = $file_info->getRealPath();
				$this->scan_directory( $subdirectory, $assoc_args );
			}
		}

		if ( $files > 50000 ) {
			WP_CLI::line( "$dir - $files files" );
		}
	}

	/**
	 * Moves files into new directories based on the first character of the filename.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Perform a dry run without any database modifications.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * @when after_wp_load
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function organize( $args, $assoc_args ) {
		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );
		$dry_run    = get_flag_value( $assoc_args, 'dry-run', false );

		$file_movements = $this->organize_directory( $base_dir, $dry_run );

		if ( $dry_run ) {
			WP_CLI::log( 'Dry run completed. No files were moved.' );
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'before', 'after' ), 'file_movements' );
		$formatter->display_items( $file_movements );
	}

	/**
	 * @param $dir
	 * @param $dry_run
	 *
	 * @return array
	 */
	private function organize_directory( $dir, $dry_run ): array {
		$iterator  = new DirectoryIterator( $dir );
		$movements = array();

		foreach ( $iterator as $file_info ) {
			if ( $file_info->isFile() ) {
				$filename   = $file_info->getFilename();
				$first_char = strtolower( $filename[0] );
				$new_dir    = $dir . '/' . $first_char . '/';

				if ( ! file_exists( $new_dir ) && ! $dry_run ) {
					mkdir( $new_dir );
				}

				$old_path = $file_info->getRealPath();
				$new_path = $new_dir . $filename;

				if ( !$dry_run ) {
					rename( $old_path, $new_path );
				}

				$movements[] = array(
					'before' => $old_path,
					'after'  => $new_path,
				);
			} elseif ( ! $file_info->isDot() && $file_info->isDir() ) {
				$subdirectory = $file_info->getRealPath();
				$movements    = array_merge( $movements, $this->organize_directory( $subdirectory, $dry_run ) );
			}
		}

		return $movements;
	}
}
