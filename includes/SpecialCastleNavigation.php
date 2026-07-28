<?php
/**
 * Special:CastleNavigation — see every room's exits, fix the broken ones, and edit them.
 *
 * The index is readable by anyone: it is diagnostics over already-public content, and a
 * broken destination is more useful to a passing reader as a red link than as a secret.
 * Editing a room needs 'editinterface', the same right that guards the data page itself.
 *
 * Exits are stored as prose with {1}..{n} placeholders plus one destination per slot, so
 * the room keeps its voice while destinations become autocompleted, checked fields —
 * nobody has to retype "Watanabe Tower:2F guard quarters" inside brackets again.
 */

namespace MediaWiki\Extension\NavigationForPagesAsRooms;

use MediaWiki\Category\Category;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Html\Html;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Widget\TitleInputWidget;


class SpecialCastleNavigation extends SpecialPage {

	/**
	 * Blank destination fields offered beyond the ones a room already has.
	 *
	 * Without these the form can only ever change the destinations a room was parsed with —
	 * there is no way to give a room an exit it did not already have, and a brand-new room
	 * would open with no destination fields at all. Three is enough for one editing pass, and
	 * saving reopens the form with three more.
	 */
	private const SPARE_SLOTS = 3;

	/** @var string Room being edited; set before the form's submit callback runs. */
	private $editingKey = '';

	/** @var int How many slot fields the form rendered, so the callback knows what to read. */
	private $editingSlotCount = 0;

	/** @var bool True when the form is making a room rather than changing one. */
	private $creatingRoom = false;

	/** @var bool True when that new room's key matches no wiki page — see showRoomForm(). */
	private $creatingWithoutPage = false;

	/** @var array<string,Title>|null Memoised roomPageIndex(), which is a whole-table query. */
	private $pageIndex = null;

	public function __construct() {
		// Read-only diagnostics over already-public content, so no restriction yet.
		// The 'editinterface' gate lands with the write path in Stage 2.
		parent::__construct( 'CastleNavigation' );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'wiki';
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->outputHeader();

		$rooms = NavigationStore::getRooms();
		$subPage = $subPage !== null ? trim( $subPage ) : '';

		if ( $subPage !== '' ) {
			// Subpages arrive title-style — "The_library" — but map keys are the room's
			// display text lowercased, so underscores have to come back out as spaces.
			$this->showRoom( $rooms, strtolower( strtr( $subPage, '_', ' ' ) ) );
		} else {
			$this->showIndex( $rooms );
		}
	}

	/**
	 * Every content page, keyed the way the renderer keys rooms.
	 *
	 * A room key is `strtolower( $title->getText() )` — lowercased AND namespace-stripped,
	 * so it cannot be turned back into a Title: "on the nature of the cloud" would resolve
	 * to mainspace "On the nature of the cloud" when the real page is
	 * "Library:On the nature of The Cloud". Going the other way is exact — fold every real
	 * page the same way the renderer does, and look the key up in that.
	 *
	 * One query over a few hundred rows, memoised: a single request can ask for the index
	 * while building the form, while rendering the room's link, and again after a save, and
	 * nothing a save writes is in a content namespace, so the answer cannot change under it.
	 *
	 * @return array<string,Title> folded key => the real Title
	 */
	private function roomPageIndex(): array {
		if ( $this->pageIndex !== null ) {
			return $this->pageIndex;
		}

		$namespaces = array_values( array_unique( array_merge(
			[ NS_MAIN ],
			$this->getConfig()->get( MainConfigNames::ContentNamespaces )
		) ) );

		$res = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getReplicaDatabase()
			->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [ 'page_namespace' => $namespaces ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$index = [];
		foreach ( $res as $row ) {
			$title = Title::makeTitle( (int)$row->page_namespace, $row->page_title );
			$key = strtolower( $title->getText() );
			// Two pages in different namespaces can fold to the same key. The renderer has
			// the same ambiguity and resolves by whichever page is being viewed, so there
			// is no "right" winner here; keep the first and don't pretend otherwise.
			$index[$key] ??= $title;
		}
		$this->pageIndex = $index;
		return $index;
	}

	/**
	 * Resolve "does this page exist" for many titles at once.
	 *
	 * The index needs existence for every room and every destination — several hundred
	 * lookups. LinkBatch resolves them in one query and primes the title cache, so the
	 * per-title checks afterwards are free.
	 *
	 * Returns the resolved Title alongside existence, so callers can link to it — a
	 * destination that doesn't exist is far more useful as a red "create this page" link
	 * than as a bare ✗ the reader can't act on.
	 *
	 * @param string[] $titleTexts
	 * @return array<string,array{title:?Title,exists:bool}> original text => resolution
	 */
	private function resolveExistence( array $titleTexts ): array {
		$linkBatch = MediaWikiServices::getInstance()
			->getLinkBatchFactory()
			->newLinkBatch();

		$titles = [];
		foreach ( array_unique( $titleTexts ) as $text ) {
			$title = Title::newFromText( $text );
			if ( $title ) {
				$titles[$text] = $title;
				$linkBatch->addObj( $title );
			}
		}
		$linkBatch->execute();

		$resolved = [];
		foreach ( $titleTexts as $text ) {
			// A title that would not parse can't exist; report it rather than skipping it.
			$title = $titles[$text] ?? null;
			$resolved[$text] = [
				'title' => $title,
				'exists' => $title !== null && $title->exists(),
			];
		}
		return $resolved;
	}

	/**
	 * A destination rendered as something the reader can act on.
	 *
	 * Existing page → normal link. Missing page → MediaWiki's red link, which lands on the
	 * create form; that is the answer to "where do I fix this?". Unparseable → plain text,
	 * because there is nothing to link to and pretending otherwise would mislead.
	 *
	 * @param array{title:?Title,exists:bool} $resolution
	 */
	private function destinationLink( array $resolution, string $rawTarget ): string {
		$title = $resolution['title'];
		if ( !$title ) {
			return Html::element( 'span', [ 'class' => 'error' ],
				$this->msg( 'castlenavigation-badtitle', $rawTarget )->text() );
		}
		$renderer = $this->getLinkRenderer();
		return $resolution['exists']
			? $renderer->makeKnownLink( $title, $title->getPrefixedText() )
			: $renderer->makeBrokenLink( $title, $title->getPrefixedText() );
	}

	/**
	 * @param array<string,string> $rooms
	 */
	private function showIndex( array $rooms ): void {
		$out = $this->getOutput();

		// Destinations are written as real wikilink targets, so Title::newFromText is
		// correct for them — unlike room keys, which need roomPageIndex(). Collect them
		// all first so every destination on the page costs one existence query.
		$pageIndex = $this->roomPageIndex();
		$wanted = [];
		$parsedRooms = [];
		foreach ( $rooms as $key => $wikitext ) {
			$targets = NavigationSlots::targets( $wikitext );
			$parsedRooms[$key] = $targets;
			foreach ( $targets as $target ) {
				$wanted[] = $target;
			}
		}
		$exists = $this->resolveExistence( $wanted );

		$rows = [];
		$roomsWithBrokenTargets = 0;
		$brokenTargets = 0;
		$roomsWithNoPage = 0;
		foreach ( $parsedRooms as $key => $targets ) {
			$missing = array_values( array_filter(
				$targets,
				static fn ( string $t ) => empty( $exists[$t]['exists'] )
			) );
			if ( $missing ) {
				$roomsWithBrokenTargets++;
				$brokenTargets += count( $missing );
			}
			$page = $pageIndex[$key] ?? null;
			if ( !$page ) {
				$roomsWithNoPage++;
			}
			$rows[] = [
				'key' => $key,
				'page' => $page,
				'exits' => count( $targets ),
				'missing' => $missing,
				// Carried so the status cell can render red links without re-resolving.
				'resolutions' => $exists,
			];
		}

		$out->addHTML( $this->summaryHtml(
			count( $rows ), $roomsWithBrokenTargets, $brokenTargets, $roomsWithNoPage ) );
		$out->addHTML( $this->indexTableHtml( $rows ) );
		$out->addHTML( $this->noEntryHtml() );
	}

	/**
	 * Pages that render <navigation/> but have no map entry.
	 *
	 * The table above can only report rooms the map already knows about. This is the
	 * other direction — the drift that used to be invisible. There is no search backend
	 * here to scan wikitext for the tag, so the renderer tags these pages into a tracking
	 * category as they parse, and this lists that category.
	 */
	private function noEntryHtml(): string {
		$html = Html::element( 'h2', [],
			$this->msg( 'castlenavigation-noentry-heading' )->text() );

		$categoryName = $this->msg( 'nfpar-tracking-category-no-entry' )->inContentLanguage()->text();
		$category = Category::newFromName( strtr( $categoryName, ' ', '_' ) );

		// The room's own page now offers this link too, but only to someone who happens to
		// walk into it. This is the same door for someone working down the list.
		$canEdit = $this->getAuthority()->isAllowed( 'editinterface' );
		$linkRenderer = $this->getLinkRenderer();

		$members = $category ? $category->getMembers() : null;
		$items = [];
		if ( $members ) {
			foreach ( $members as $title ) {
				$item = $linkRenderer->makeLink( $title );
				if ( $canEdit ) {
					// Fold the title exactly as the renderer folds it, for the same reason
					// roomPageIndex() does: the key is lowercased and namespace-stripped, so
					// it cannot be recovered from anything but the real Title.
					$item .= ' ' . $linkRenderer->makeKnownLink(
						$this->getPageTitle( strtolower( $title->getText() ) ),
						$this->msg( 'castlenavigation-setupexits' )->text() );
				}
				$items[] = Html::rawElement( 'li', [], $item );
			}
		}

		if ( !$items ) {
			// Deliberately not phrased as "everything is fine": the category only fills
			// as pages are parsed, so an empty list means "nothing seen yet", not "none".
			return $html . Html::element( 'p', [],
				$this->msg( 'castlenavigation-noentry-empty' )->text() );
		}

		return $html . Html::rawElement( 'ul', [], implode( '', $items ) );
	}

	private function summaryHtml(
		int $roomCount, int $roomsWithBrokenTargets, int $brokenTargets, int $roomsWithNoPage
	): string {
		$msg = $this->msg( 'castlenavigation-totals' )
			->numParams( $roomCount, $roomsWithBrokenTargets, $brokenTargets, $roomsWithNoPage )
			->escaped();
		return Html::rawElement( 'p', [ 'class' => 'nfpar-summary' ], $msg );
	}

	/**
	 * @param array<int,array{key:string,page:?Title,exits:int,missing:string[],resolutions:array}> $rows
	 */
	private function indexTableHtml( array $rows ): string {
		$html = Html::openElement( 'table', [
			// 'sortable' gives column sorting for free; 'nfpar-index' is our hook for
			// "show only broken rooms" filtering.
			'class' => 'wikitable sortable nfpar-index',
		] );

		$html .= Html::openElement( 'tr' );
		foreach ( [ 'room', 'page', 'exits', 'broken', 'status' ] as $col ) {
			$html .= Html::element( 'th', [], $this->msg( "castlenavigation-col-$col" )->text() );
		}
		$html .= Html::closeElement( 'tr' );

		foreach ( $rows as $row ) {
			$html .= $this->indexRowHtml( $row );
		}

		return $html . Html::closeElement( 'table' );
	}

	/**
	 * @param array{key:string,page:?Title,exits:int,missing:string[],resolutions:array} $row
	 */
	private function indexRowHtml( array $row ): string {
		$page = $row['page'];

		if ( $row['missing'] ) {
			// Red links, so a broken destination can be created straight from this table
			// rather than leaving the reader to work out where to go.
			$links = [];
			foreach ( $row['missing'] as $target ) {
				$links[] = $this->destinationLink(
					$row['resolutions'][$target] ?? [ 'title' => null, 'exists' => false ],
					$target );
			}
			$status = $this->msg( 'castlenavigation-status-brokentargets' )->escaped()
				. ' ' . implode( ', ', $links );
		} elseif ( !$page ) {
			$status = Html::element( 'span', [], $this->msg( 'castlenavigation-status-nopage' )->text() );
		} else {
			$status = Html::element( 'span', [], $this->msg( 'castlenavigation-status-ok' )->text() );
		}

		// Only a broken destination is a defect. A room with no page of its own is
		// ordinary — plenty of NFPaR entries describe places that were never written up —
		// so it gets reported without being flagged red.
		$detail = $this->getPageTitle( $row['key'] );
		$linkRenderer = $this->getLinkRenderer();

		$cells = Html::rawElement( 'td', [],
			$linkRenderer->makeLink( $detail, $row['key'] ) );
		$cells .= Html::rawElement( 'td', [],
			$page
				? $linkRenderer->makeLink( $page, $page->getPrefixedText() )
				: Html::element( 'span', [ 'class' => 'nfpar-nopage' ], '—' ) );
		$cells .= Html::element( 'td', [], (string)$row['exits'] );
		$cells .= Html::element( 'td', [], (string)count( $row['missing'] ) );
		$cells .= Html::rawElement( 'td', [], $status );

		return Html::rawElement( 'tr',
			$row['missing'] ? [ 'class' => 'nfpar-broken' ] : [],
			$cells );
	}

	/**
	 * @param array<string,string> $rooms
	 */
	private function showRoom( array $rooms, string $key ): void {
		$out = $this->getOutput();

		if ( !isset( $rooms[$key] ) ) {
			// A page can render <navigation/> long before anyone gives it exits, so "no such
			// room" is an ordinary state with an obvious next step — for the people allowed
			// to take it. Everyone else is told, and gets no form.
			if ( $this->getAuthority()->isAllowed( 'editinterface' ) ) {
				$this->showRoomForm( $key, '', true );
				return;
			}
			$out->addHTML( Html::element( 'p', [ 'class' => 'error' ],
				$this->msg( 'castlenavigation-noroom', $key )->text() ) );
			$out->addHTML( Html::rawElement( 'p', [],
				$this->getLinkRenderer()->makeLink(
					$this->getPageTitle(),
					$this->msg( 'castlenavigation-backtoindex' )->text() ) ) );
			return;
		}

		// Sysops get the editing form; everyone else the read-only view. Gating here rather
		// than on the whole special page keeps the audit useful to anyone who wants to look.
		if ( $this->getAuthority()->isAllowed( 'editinterface' ) ) {
			$this->showRoomForm( $key, $rooms[$key] );
		} else {
			$out->addHTML( $this->roomDetailHtml( $key, $rooms[$key] ) );
		}
	}

	/**
	 * The editing form for one room.
	 *
	 * Prose keeps the room's voice, with {1}..{n} standing in for destinations; each
	 * destination is an autocompleting title field. The destination fields deliberately do
	 * NOT use 'exists' => true, which would refuse the save outright: a red link to a page
	 * you intend to write is legitimate, so a missing destination warns and asks for one
	 * confirmation instead of being forbidden.
	 */
	private function showRoomForm( string $key, string $wikitext, bool $creating = false ): void {
		$parsed = NavigationSlots::parse( $wikitext );
		$this->editingKey = $key;
		$this->editingSlotCount = count( $parsed['slots'] ) + self::SPARE_SLOTS;
		$this->creatingRoom = $creating;

		if ( $creating ) {
			// Rooms are matched by folding a page's title, never by parsing the key back into
			// one, so roomPageIndex() is the only honest way to ask "could anything ever hit
			// this key?". A key that folds from no page is dead data: invisible, permanent,
			// and exactly the failure that hid atilliator's workshop for years. Warn hard —
			// but allow it, so a room can be laid out before its page is written.
			$page = $this->roomPageIndex()[$key] ?? null;
			$this->creatingWithoutPage = $page === null;

			$this->getOutput()->addHTML( $this->creatingWithoutPage
				? Html::warningBox( $this->msg( 'castlenavigation-create-nopage', $key )->escaped() )
				: Html::rawElement( 'p', [],
					$this->msg( 'castlenavigation-create-intro' )->escaped() . ' '
					. $this->getLinkRenderer()->makeKnownLink( $page, $page->getPrefixedText() ) ) );
		}

		$fields = [
			'prose' => [
				'type' => 'textarea',
				'label-message' => 'castlenavigation-field-prose',
				'help-message' => 'castlenavigation-field-prose-help',
				'default' => $parsed['prose'],
				'rows' => 4,
				'required' => true,
			],
		];

		for ( $n = 1; $n <= $this->editingSlotCount; $n++ ) {
			$slot = $parsed['slots'][$n] ?? null;
			$fields["target-$n"] = [
				'type' => 'title',
				// Autocompletes against real pages, but does not block a red link.
				'exists' => false,
				// Nothing is required: a filled slot the prose never mentions, and a {n} with
				// no slot behind it, are both caught at save with a message that explains
				// itself. 'required' here would only produce a browser tooltip on the spares.
				'required' => false,
				'label' => $this->msg( 'castlenavigation-field-target' )->numParams( $n )->text(),
				'default' => $slot['target'] ?? '',
			];
			$fields["label-$n"] = [
				'type' => 'text',
				'label' => $this->msg( 'castlenavigation-field-label' )->numParams( $n )->text(),
				'default' => $slot['label'] ?? '',
			];
		}

		$fields['allowmissing'] = [
			'type' => 'check',
			'label-message' => 'castlenavigation-field-allowmissing',
			'default' => false,
		];

		if ( $this->creatingWithoutPage ) {
			$fields['createanyway'] = [
				'type' => 'check',
				'label-message' => 'castlenavigation-field-createanyway',
				'default' => false,
			];
		}

		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() );
		$form->setWrapperLegend( $key );
		$form->setSubmitTextMsg( 'castlenavigation-save' );
		$form->setSubmitCallback( [ $this, 'onRoomSubmit' ] );
		$form->show();

		$this->getOutput()->addHTML( Html::rawElement( 'p', [],
			$this->getLinkRenderer()->makeLink(
				$this->getPageTitle(),
				$this->msg( 'castlenavigation-backtoindex' )->text() ) ) );
	}

	/**
	 * Validate and save one room. Returns true, or a message for HTMLForm to display.
	 *
	 * @param array $data
	 * @return bool|string|array
	 */
	public function onRoomSubmit( array $data ) {
		// Asked before anything else: if the key can never be reached, the room's contents
		// are beside the point.
		if ( $this->creatingWithoutPage && empty( $data['createanyway'] ) ) {
			return $this->msg( 'castlenavigation-error-nopage', $this->editingKey )->text();
		}

		$slots = [];
		for ( $n = 1; $n <= $this->editingSlotCount; $n++ ) {
			$target = trim( $data["target-$n"] ?? '' );
			$label = $data["label-$n"] ?? '';

			if ( $target === '' ) {
				// An untouched spare slot; skip it. build() drops slots the prose does not
				// reference anyway, so the numbering of the surviving slots stays intact and
				// nothing needs renumbering.
				if ( trim( $label ) !== '' ) {
					// A label with no destination is a half-filled row rather than an unused
					// one — say so instead of silently discarding what was typed.
					return $this->msg( 'castlenavigation-error-labelnotarget' )
						->numParams( $n )->text();
				}
				continue;
			}

			$slots[$n] = [
				'target' => $target,
				// An empty label means "no pipe" — [[foo]] rather than [[foo|]] — so that a
				// round trip through the form doesn't quietly rewrite every plain link.
				'label' => $label === '' ? null : $label,
			];
		}

		$prose = $data['prose'];

		// Every {n} in the prose must have a slot behind it, or the saved wikitext would
		// contain a literal "{5}" for readers to trip over.
		preg_match_all( '/\{(\d+)\}/', $prose, $m );
		$referenced = array_map( 'intval', $m[1] );
		$unknown = array_values( array_unique( array_diff( $referenced, array_keys( $slots ) ) ) );
		if ( $unknown ) {
			return $this->msg( 'castlenavigation-error-unknownslot' )
				->params( $this->getLanguage()->commaList( array_map( static fn ( $i ) => "{{$i}}", $unknown ) ) )
				->text();
		}

		// A slot the prose never mentions would silently vanish from the room. Say so
		// rather than dropping someone's destination without comment.
		$orphans = array_values( array_diff( array_keys( $slots ), $referenced ) );
		if ( $orphans ) {
			return $this->msg( 'castlenavigation-error-orphanslot' )
				->params( $this->getLanguage()->commaList( array_map( static fn ( $i ) => "{{$i}}", $orphans ) ) )
				->text();
		}

		// Warn-not-block: name the destinations that don't exist and require one tick.
		if ( !$data['allowmissing'] ) {
			$missing = [];
			foreach ( $slots as $slot ) {
				$title = Title::newFromText( $slot['target'] );
				if ( !$title || !$title->exists() ) {
					$missing[] = $this->destinationLink(
						[ 'title' => $title, 'exists' => false ],
						$slot['target'] );
				}
			}
			if ( $missing ) {
				// Returned as a plain string on purpose. HTMLForm::getErrorsOrWarnings()
				// puts a string straight into Html::errorBox() without escaping, which is
				// what lets these be real red links — click one and you land on the create
				// form for that page. A Message would be ->parse()d instead, and relying on
				// parameter-substitution to survive that is fiddlier than building the
				// links here, where destinationLink() already escapes the titles.
				return $this->msg( 'castlenavigation-error-missingtargets' )->escaped()
					. ' ' . implode( ', ', $missing ) . ' '
					. $this->msg( 'castlenavigation-error-missingtargets-hint' )->escaped();
			}
		}

		$status = NavigationStore::saveRoom(
			$this->editingKey,
			NavigationSlots::build( $prose, $slots ),
			$this->getAuthority(),
			$this->creatingRoom
		);
		if ( !$status->isOK() ) {
			return $status->getMessage()->text();
		}

		$out = $this->getOutput();
		$out->addHTML( Html::element( 'p', [ 'class' => 'success' ],
			$this->msg( $this->creatingRoom
				? 'castlenavigation-created'
				: 'castlenavigation-saved' )->text() ) );

		// The point of saving is to go and look at the room, so offer it directly rather
		// than making the editor navigate back through the index to find it again.
		$page = $this->roomPageIndex()[$this->editingKey] ?? null;
		if ( $page ) {
			$out->addHTML( Html::rawElement( 'p', [],
				$this->msg( 'castlenavigation-viewroom' )->escaped() . ' '
				. $this->getLinkRenderer()->makeKnownLink( $page, $page->getPrefixedText() ) ) );
		}

		return true;
	}

	private function roomDetailHtml( string $key, string $wikitext ): string {
		$parsed = NavigationSlots::parse( $wikitext );
		$exists = $this->resolveExistence( NavigationSlots::targets( $wikitext ) );

		// The destination fields are real TitleInputWidgets, just disabled — so what gets
		// judged here is the layout Stage 2 will actually ship, not an approximation.
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addModules( [ 'mediawiki.widgets' ] );

		$html = Html::element( 'h2', [], $key );

		$html .= Html::element( 'h3', [],
			$this->msg( 'castlenavigation-prose-heading' )->text() );
		$html .= Html::element( 'textarea', [
			'class' => 'nfpar-prose',
			'rows' => 4,
			'readonly' => true,
			'disabled' => true,
		], $parsed['prose'] );

		$html .= Html::element( 'h3', [],
			$this->msg( 'castlenavigation-destinations-heading' )->text() );
		$html .= $this->slotsTableHtml( $parsed['slots'], $exists );

		$html .= Html::rawElement( 'p', [],
			$this->getLinkRenderer()->makeLink(
				$this->getPageTitle(),
				$this->msg( 'castlenavigation-backtoindex' )->text() ) );

		return $html;
	}

	/**
	 * @param array<int,array{target:string,label:?string}> $slots
	 * @param array<string,bool> $exists
	 */
	private function slotsTableHtml( array $slots, array $exists ): string {
		if ( !$slots ) {
			return Html::element( 'p', [],
				$this->msg( 'castlenavigation-noexits' )->text() );
		}

		$html = Html::openElement( 'table', [ 'class' => 'wikitable nfpar-slots' ] );
		$html .= Html::openElement( 'tr' );
		foreach ( [ 'slot', 'destination', 'shownas', 'page' ] as $col ) {
			$html .= Html::element( 'th', [], $this->msg( "castlenavigation-col-$col" )->text() );
		}
		$html .= Html::closeElement( 'tr' );

		foreach ( $slots as $n => $slot ) {
			$resolution = $exists[$slot['target']] ?? [ 'title' => null, 'exists' => false ];
			$ok = $resolution['exists'];

			// Disabled in Stage 1, but otherwise the widget Stage 2 will use: an
			// autocompleting title input. Stage 2 wraps it in HTMLTitleTextField with
			// 'exists' => true, which refuses to save a destination that isn't a real page.
			$input = new TitleInputWidget( [
				'value' => $slot['target'],
				'disabled' => true,
				'classes' => [ 'nfpar-destination' ],
			] );

			$cells = Html::element( 'td', [], '{' . $n . '}' );
			$cells .= Html::rawElement( 'td', [], (string)$input );
			$cells .= Html::element( 'td', [], $slot['label'] ?? '' );
			// The link, not a bare tick: if the page is missing this is the red link that
			// opens the create form, which is the whole answer to "where do I fix it?".
			$cells .= Html::rawElement( 'td', [],
				( $ok ? '✓ ' : '✗ ' ) . $this->destinationLink( $resolution, $slot['target'] ) );

			$html .= Html::rawElement( 'tr',
				$ok ? [] : [ 'class' => 'nfpar-broken' ],
				$cells );
		}

		return $html . Html::closeElement( 'table' );
	}
}
