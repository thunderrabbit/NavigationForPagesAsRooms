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

use MediaWiki\Content\JsonContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Revision\SlotRecord;
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

	/** Drop the process cache. Only useful right after a save within the same request. */
	public static function clearCache(): void {
		self::$rooms = null;
	}
}
