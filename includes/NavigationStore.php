<?php
/**
 * Where the room exits live: a JSON page on the wiki.
 *
 * The exits used to be a hand-edited PHP array, which meant shell access and a deploy to
 * change a room, and left MediaWiki with no way to know anything had changed. Holding them
 * in `MediaWiki:Castle-navigation.json` fixes all three at once:
 *
 *  - `MediaWiki:*.json` gets CONTENT_MODEL_JSON automatically, so malformed JSON is
 *    rejected at save time rather than breaking every room at render time.
 *  - The MediaWiki namespace is `editinterface`-restricted, so only sysops can touch it.
 *  - Page history gives diffs, blame and one-click revert for free.
 *  - Registering the page with ParserOutput::addTemplate() makes an edit invalidate every
 *    room that reads it — no purging, no deploy-time cache dance.
 *
 * The shape is deliberately identical to the old PHP array — `room key => exit wikitext` —
 * so the migration is a straight copy and the renderer barely changes. Splitting exits into
 * prose and destinations is a concern of the editing form, not of storage.
 */

namespace MediaWiki\Extension\NavigationForPagesAsRooms;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\JsonContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use NavigationForPagesAsRooms;

class NavigationStore {

	/** Page holding the navigation data. */
	public const DATA_PAGE = 'MediaWiki:Castle-navigation.json';

	/** @var array<string,string>|null Process cache; the renderer hits this per page view. */
	private static $rooms = null;

	/**
	 * The data page as a Title, or null if it somehow cannot be parsed.
	 */
	public static function getDataTitle(): ?Title {
		return Title::newFromText( self::DATA_PAGE );
	}

	/**
	 * All rooms, keyed the way the renderer keys them (lowercased room title).
	 *
	 * Falls back to the extension's built-in array when the data page does not exist or
	 * does not parse. That fallback is what makes this migration safe to deploy before the
	 * page is created — and what keeps the Castle navigable if the page is ever deleted.
	 *
	 * @return array<string,string> lowercased room key => exit wikitext
	 */
	public static function getRooms(): array {
		if ( self::$rooms !== null ) {
			return self::$rooms;
		}

		$fromPage = self::readDataPage();
		// An empty page is treated as "no data", not as "no rooms" — otherwise a blank save
		// would silently strip navigation from the whole Castle.
		self::$rooms = $fromPage ?: NavigationForPagesAsRooms::getRooms();
		return self::$rooms;
	}

	/**
	 * True when the exits are coming from the wiki page rather than the built-in array.
	 */
	public static function isUsingDataPage(): bool {
		return (bool)self::readDataPage();
	}

	/**
	 * Read and decode the data page.
	 *
	 * @return array<string,string> empty when missing, unparseable, or not a flat map
	 */
	private static function readDataPage(): array {
		$title = self::getDataTitle();
		if ( !$title || !$title->exists() ) {
			return [];
		}

		$content = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getKnownCurrentRevision( $title )
			?->getContent( SlotRecord::MAIN );
		if ( !$content instanceof JsonContent ) {
			return [];
		}

		$status = $content->getData();
		if ( !$status->isOK() ) {
			return [];
		}

		$data = $status->getValue();
		// getData() hands back stdClass for objects; normalise and drop anything that isn't
		// a plain string, so one bad entry can't take the whole Castle down.
		$rooms = [];
		foreach ( (array)$data as $key => $value ) {
			if ( is_string( $value ) ) {
				$rooms[ strtolower( (string)$key ) ] = $value;
			}
		}
		return $rooms;
	}

	/**
	 * Tell the parser this page's output depends on the data page.
	 *
	 * This is the whole reason a wiki page beats a database table here: editing the data
	 * page queues an HTMLCacheUpdateJob over everything registered this way, which bumps
	 * page_touched and guarantees a parser-cache miss with fresh exits on the next view.
	 */
	public static function registerDependency( Parser $parser ): void {
		$title = self::getDataTitle();
		if ( !$title || !$title->exists() ) {
			return;
		}
		$revision = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getKnownCurrentRevision( $title );
		$parser->getOutput()->addTemplate(
			$title,
			$title->getArticleID(),
			$revision ? $revision->getId() : 0
		);
	}

	/**
	 * Write one room's exits back to the data page.
	 *
	 * Reads the whole map first and replaces a single key, so a save can never drop the
	 * other 110 rooms — and so two people editing different rooms don't clobber each other
	 * beyond the usual last-write-wins on the page itself.
	 *
	 * @param string $key Lowercased room key; must already exist.
	 * @param string $wikitext The room's new exit text.
	 * @param Authority $performer Who is making the edit.
	 * @return Status
	 */
	public static function saveRoom( string $key, string $wikitext, Authority $performer ): Status {
		$title = self::getDataTitle();
		if ( !$title ) {
			return Status::newFatal( 'castlenavigation-save-badpage', self::DATA_PAGE );
		}

		// Authorise here as well as at the form, rather than trusting the caller to have
		// done it. Special:CastleNavigation does gate on this today, but that is one call
		// site away from being wrong; a maintenance script, an API module or a future hook
		// could reach this method without passing through the form.
		//
		// authorizeWrite rather than isAllowed: this is the actual write, so it should also
		// honour blocks and rate limits, and it is checked against the real page — which
		// carries the MediaWiki-namespace 'editinterface' requirement by definition, so
		// there is no second right to keep in sync with the form's gate.
		$permissionStatus = PermissionStatus::newEmpty();
		if ( !$performer->authorizeWrite( 'edit', $title, $permissionStatus ) ) {
			return Status::wrap( $permissionStatus );
		}

		$rooms = self::readDataPage() ?: NavigationForPagesAsRooms::getRooms();
		if ( !array_key_exists( $key, $rooms ) ) {
			return Status::newFatal( 'castlenavigation-noroom', $key );
		}

		if ( $rooms[$key] === $wikitext ) {
			// Nothing to do. Saving anyway would put a null edit in the page history and
			// invalidate every room's cache for no reason.
			return Status::newGood( false );
		}

		$rooms[$key] = $wikitext;
		ksort( $rooms );

		$json = json_encode( $rooms,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( $json === false ) {
			return Status::newFatal( 'castlenavigation-save-badjson' );
		}

		$services = MediaWikiServices::getInstance();
		$updater = $services->getWikiPageFactory()
			->newFromTitle( $title )
			->newPageUpdater( $performer->getUser() );
		$updater->setContent( SlotRecord::MAIN, new JsonContent( $json ) );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment(
			'Castle navigation: ' . $key
		) );

		$status = $updater->getStatus() ?? Status::newGood();
		if ( $status->isOK() ) {
			self::clearCache();
		}
		return $status;
	}

	/** Drop the process cache. Only useful right after a save within the same request. */
	public static function clearCache(): void {
		self::$rooms = null;
	}
}
