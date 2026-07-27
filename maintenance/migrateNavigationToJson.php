<?php
/**
 * One-time migration: copy the built-in navigation map onto the wiki as JSON.
 *
 * After this runs, MediaWiki:Castle-navigation.json is the source of truth and the array in
 * NavigationForPagesAsRooms.php is only a fallback. Editing navigation stops needing shell
 * access, a deploy, or a cache purge.
 *
 *   php maintenance/run.php \
 *     extensions/NavigationForPagesAsRooms/maintenance/migrateNavigationToJson.php --dry-run
 *
 * Safe to re-run: it refuses to overwrite an existing page unless --force is given, so it
 * cannot silently discard edits made through the form.
 */

use MediaWiki\Content\JsonContent;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Extension\NavigationForPagesAsRooms\NavigationSlots;
use MediaWiki\Extension\NavigationForPagesAsRooms\NavigationStore;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

$IP = getenv( 'MW_INSTALL_PATH' ) ?: __DIR__ . '/../../..';
require_once "$IP/maintenance/Maintenance.php";

class MigrateNavigationToJson extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Copy the built-in navigation map to MediaWiki:Castle-navigation.json' );
		$this->addOption( 'dry-run', 'Show what would be written, change nothing' );
		$this->addOption( 'force', 'Overwrite the page even if it already exists' );
		$this->requireExtension( 'NavigationForPagesAsRooms' );
	}

	public function execute() {
		$rooms = NavigationForPagesAsRooms::getRooms();
		$this->output( "Built-in map: " . count( $rooms ) . " rooms\n" );

		// The form round-trips exits through NavigationSlots, so anything that cannot make
		// that trip would be silently mangled the first time it is edited. Better to find
		// out now, while the PHP array is still authoritative.
		$bad = [];
		foreach ( $rooms as $key => $wikitext ) {
			$parsed = NavigationSlots::parse( $wikitext );
			if ( NavigationSlots::build( $parsed['prose'], $parsed['slots'] ) !== $wikitext ) {
				$bad[] = $key;
			}
		}
		if ( $bad ) {
			$this->fatalError( "These rooms do not survive a slot round-trip; migrating would\n"
				. "risk mangling them on first edit:\n  " . implode( "\n  ", $bad ) );
		}
		$this->output( "Round-trip check: all " . count( $rooms ) . " rooms OK\n" );

		$title = Title::newFromText( NavigationStore::DATA_PAGE );
		if ( !$title ) {
			$this->fatalError( 'Could not parse ' . NavigationStore::DATA_PAGE );
		}

		if ( $title->exists() && !$this->hasOption( 'force' ) ) {
			$this->fatalError( $title->getPrefixedText() . " already exists.\n"
				. "Refusing to overwrite — it may hold edits made through the form.\n"
				. "Pass --force if you really mean to replace it." );
		}

		// Sorted so the page reads like a reference and diffs stay legible.
		ksort( $rooms );
		$json = json_encode( $rooms, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$content = new JsonContent( $json );

		$status = $content->getData();
		if ( !$status->isOK() ) {
			$this->fatalError( 'Generated JSON does not parse — aborting.' );
		}

		if ( $this->hasOption( 'dry-run' ) ) {
			$this->output( "\n--- dry run; nothing written ---\n" );
			$this->output( "Would write " . strlen( $json ) . " bytes to "
				. $title->getPrefixedText() . "\n" );
			$this->output( "First 400 bytes:\n" . substr( $json, 0, 400 ) . "\n...\n" );
			return;
		}

		// The registered system account core scripts edit as, so the revision is properly
		// attributed instead of landing as an anon or an unregistered name.
		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		$updater = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $title )
			->newPageUpdater( $user );
		$updater->setContent( SlotRecord::MAIN, $content );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment(
			'Import Castle navigation from NavigationForPagesAsRooms'
		) );

		if ( !$updater->getStatus()->isOK() ) {
			$this->fatalError( 'Save failed: ' . $updater->getStatus()->getMessage()->text() );
		}

		$this->output( "Wrote " . count( $rooms ) . " rooms to " . $title->getPrefixedText() . "\n" );
	}
}

$maintClass = MigrateNavigationToJson::class;
require_once RUN_MAINTENANCE_IF_MAIN;
