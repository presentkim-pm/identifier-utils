<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 * @noinspection SpellCheckingInspection
 */

declare(strict_types=1);

namespace kim\present\register\itemidentifier;

use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;

final class ItemIdentifierRegister{
    private function __construct(){ }

    /** @noinspection PhpUndefinedFieldInspection */
    public static function register(string $stringId, int $legacyId, int $legacyMeta = -1, int $runtimeId = -1) : void{
        if($runtimeId === -1){
            $runtimeId = $legacyId - 5000;
        }
        (function() use ($stringId, $runtimeId, $legacyId, $legacyMeta){ //HACK : Closure bind hack to access inaccessible members
            if($legacyMeta === -1){
                // simple mapping - When the same item has multiple meta. ex) IronHoe
                /** @see ItemTranslator::simpleCoreToNetMapping */
                /** @see ItemTranslator::simpleNetToCoreMapping */
                $this->simpleCoreToNetMapping[$legacyId] = $runtimeId;
                $this->simpleNetToCoreMapping[$runtimeId] = $legacyId;
            }else{
                // complex mapping - When items are classified according to meta. ex) Bucket
                /** @see ItemTranslator::complexCoreToNetMapping */
                /** @see ItemTranslator::complexNetToCoreMapping */
                $this->complexCoreToNetMapping[$legacyId][$legacyMeta] = $runtimeId;
                $this->complexNetToCoreMapping[$runtimeId] = [$legacyId, $legacyMeta];
            }
        })->call(ItemTranslator::getInstance());

        (function() use ($stringId, $runtimeId){ //HACK : Closure bind hack to access inaccessible members
            /** @see ItemTypeDictionary::itemTypes */
            /** @see ItemTypeDictionary::intToStringIdMap */
            /** @see ItemTypeDictionary::stringToIntMap */
            $this->itemTypes[] = new ItemTypeEntry($stringId, $runtimeId, true);
            $this->stringToIntMap[$stringId] = $runtimeId;
            $this->intToStringIdMap[$runtimeId] = $stringId;
        })->call(GlobalItemTypeDictionary::getInstance()->getDictionary());
    }
}