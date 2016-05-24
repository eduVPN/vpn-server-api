<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\Server\Acl;

use fkooman\Config\Reader;

class StaticAcl implements AclInterface
{
    /** @var array */
    private $userGroups;

    public function __construct(Reader $configReader)
    {
        $this->userGroups = $configReader->v('StaticAcl', false, []);
    }

    public function getGroups($userId)
    {
        if (!is_array($this->userGroups)) {
            return [];
        }

        $memberOf = [];
        foreach ($this->userGroups as $groupId => $groupMembers) {
            if (!is_array($groupMembers)) {
                continue;
            }
            if (in_array($userId, $groupMembers)) {
                $memberOf[] = $groupId;
            }
        }

        return $memberOf;
    }
}
