<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAV\Tests\unit\Service;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\Service\DefaultContactService;
use OCP\App\IAppManager;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Test\TestCase;

class DefaultContactServiceTest extends TestCase {
	protected DefaultContactService $service;
	protected CardDavBackend&MockObject $cardDav;
	protected IAppManager&MockObject $appManager;
	protected IAppDataFactory&MockObject $appDataFactory;
	protected LoggerInterface&MockObject $logger;
	protected IAppConfig&MockObject $config;

	protected function setUp(): void {
		parent::setUp();

		$this->cardDav = $this->createMock(CardDavBackend::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->config = $this->createMock(IAppConfig::class);

		$this->service = new DefaultContactService(
			$this->cardDav,
			$this->appManager,
			$this->appDataFactory,
			$this->config,
			$this->logger,
		);
	}

	public function testCreateDefaultContactWithInvalidCard(): void {
		// Invalid vCard missing required FN property
		$vcardContent = "BEGIN:VCARD\nVERSION:3.0\nEND:VCARD";
		$this->config->method('getValueString')->willReturn('yes');
		$appData = $this->createMock(IAppData::class);
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getContent')->willReturn($vcardContent);
		$folder->method('getFile')->willReturn($file);
		$appData->method('getFolder')->willReturn($folder);
		$this->appDataFactory->method('get')->willReturn($appData);

		$this->logger->expects($this->once())
			->method('error')
			->with('Default contact is invalid', $this->anything());

		$this->cardDav->expects($this->never())
			->method('createCard');

		$this->service->createDefaultContact(123);
	}

	public function testUidAndRevAreUpdated(): void {
		$originalUid = 'original-uid';
		$originalRev = '20200101T000000Z';
		$vcardContent = "BEGIN:VCARD\nVERSION:3.0\nFN:Test User\nUID:$originalUid\nREV:$originalRev\nEND:VCARD";

		$this->config->method('getValueString')->willReturn('yes');
		$appData = $this->createMock(IAppData::class);
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getContent')->willReturn($vcardContent);
		$folder->method('getFile')->willReturn($file);
		$appData->method('getFolder')->willReturn($folder);
		$this->appDataFactory->method('get')->willReturn($appData);

		$capturedCardData = null;
		$this->cardDav->expects($this->once())
			->method('createCard')
			->with(
				$this->anything(),
				$this->anything(),
				$this->callback(function ($cardData) use (&$capturedCardData) {
					$capturedCardData = $cardData;
					return true;
				}),
				$this->anything()
			)->willReturn(null);

		$this->service->createDefaultContact(123);

		$vcard = \Sabre\VObject\Reader::read($capturedCardData);
		$this->assertNotEquals($originalUid, $vcard->UID->getValue());
		$this->assertTrue(Uuid::isValid($vcard->UID->getValue()));
		$this->assertNotEquals($originalRev, $vcard->REV->getValue());
	}

	public function testDefaultContactFileDoesNotExist(): void {
		$appData = $this->createMock(IAppData::class);
		$this->config->method('getValueString')->willReturn('yes');
		$appData->method('getFolder')->willThrowException(new NotFoundException());
		$this->appDataFactory->method('get')->willReturn($appData);

		$this->cardDav->expects($this->never())
			->method('createCard');

		$this->service->createDefaultContact(123);
	}

	public function testUidAndRevAreAddedIfMissing(): void {
		$vcardContent = "BEGIN:VCARD\nVERSION:3.0\nFN:Test User\nEND:VCARD";

		$this->config->method('getValueString')->willReturn('yes');
		$appData = $this->createMock(IAppData::class);
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getContent')->willReturn($vcardContent);
		$folder->method('getFile')->willReturn($file);
		$appData->method('getFolder')->willReturn($folder);
		$this->appDataFactory->method('get')->willReturn($appData);

		$capturedCardData = 'new-card-data';

		$this->cardDav
			->expects($this->once())
			->method('createCard')
			->with(
				$this->anything(),
				$this->anything(),
				$this->callback(function ($cardData) use (&$capturedCardData) {
					$capturedCardData = $cardData;
					return true;
				}),
				$this->anything()
			);

		$this->service->createDefaultContact(123);
		$vcard = \Sabre\VObject\Reader::read($capturedCardData);

		$this->assertNotNull($vcard->REV);
		$this->assertNotNull($vcard->UID);
		$this->assertTrue(Uuid::isValid($vcard->UID->getValue()));
	}

	public function testDefaultContactIsNotCreatedIfEnabled(): void {
		$this->config->method('getValueString')->willReturn('no');
		$this->logger->expects($this->never())
			->method('error');
		$this->cardDav->expects($this->never())
			->method('createCard');

		$this->service->createDefaultContact(123);
	}
}
