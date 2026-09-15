<?php

declare(strict_types=1);

namespace Ws\Faq\Service;

use Bitrix\Main\Context;

final class GuestIdentifierService
{
	public function getGuestHash(): string
	{
		$request = Context::getCurrent()->getRequest();

		$bxUserId = (string)(
			$request->getCookieRaw('BX_USER_ID')
			?? $request->getCookie('BX_USER_ID')
			?? ''
		);
		$ip = (string)$request->getRemoteAddress();
		$userAgent = (string)$request->getUserAgent();

		$fingerprint = hash('sha256', $ip . "\0" . $userAgent);

		return hash('sha256', $bxUserId . '|' . $fingerprint);
	}
}
