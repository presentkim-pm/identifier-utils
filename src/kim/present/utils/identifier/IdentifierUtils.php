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

namespace kim\present\utils\identifier;

use OverflowException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;

final class IdentifierUtils{
    /** @internal */
    public static int $runtimeIdCursor = 0xfff;

    private function __construct(){ }

    /** @noinspection PhpUndefinedFieldInspection */
    public static function registerItem(string $stringId, int $legacyId, int $legacyMeta = -1) : void{
        (function() use ($stringId, $legacyId, $legacyMeta){ //HACK : Closure bind hack to access inaccessible members
            $runtimeId = null;
            while($runtimeId === null){
                if(IdentifierUtils::$runtimeIdCursor >= 0xffff){
                    throw new OverflowException("There are no more item runtime ids available.");
                }
                $cursor = IdentifierUtils::$runtimeIdCursor++;
                /**
                 * @see ItemTranslator::simpleNetToCoreMapping
                 * @see ItemTranslator::complexNetToCoreMapping
                 */
                if(!isset($this->simpleNetToCoreMapping[$cursor]) && !isset($this->complexNetToCoreMapping[$cursor])){
                    $runtimeId = $cursor;
                }
            }
            if($legacyMeta === -1){
                // simple mapping - When the same item has multiple meta. ex) IronHoe
                /** @see ItemTranslator::simpleCoreToNetMapping */
                $this->simpleCoreToNetMapping[$legacyId] = $runtimeId;

                /** @see ItemTranslator::simpleNetToCoreMapping */
                $this->simpleNetToCoreMapping[$runtimeId] = $legacyId;
            }else{
                // complex mapping - When items are classified according to meta. ex) Bucket
                /** @see ItemTranslator::complexCoreToNetMapping */
                $this->complexCoreToNetMapping[$legacyId][$legacyMeta] = $runtimeId;

                /** @see ItemTranslator::complexNetToCoreMapping */
                $this->complexNetToCoreMapping[$runtimeId] = [$legacyId, $legacyMeta];
            }

            (function() use ($stringId, $runtimeId){ //HACK : Closure bind hack to access inaccessible members
                /** @see ItemTypeDictionary::itemTypes */
                $this->itemTypes[] = new ItemTypeEntry($stringId, $runtimeId, true);

                /** @see ItemTypeDictionary::intToStringIdMap */
                $this->stringToIntMap[$stringId] = $runtimeId;

                /** @see ItemTypeDictionary::stringToIntMap */
                $this->intToStringIdMap[$runtimeId] = $stringId;
            })->call(GlobalItemTypeDictionary::getInstance()->getDictionary());
        })->call(ItemTranslator::getInstance());
    }

    public static function registerEntity(string $entityIdentifier) : void{
        $availableActorIdentifiersPacket = StaticPacketCache::getInstance()->getAvailableActorIdentifiers();
        /** @var CompoundTag $identifiersNbt */
        $identifiersNbt = $availableActorIdentifiersPacket->identifiers->getRoot();
        $idList = $identifiersNbt->getListTag("idlist");
        if($idList === null){
            throw new RuntimeException("Not found idlist tag in AvailableActorIdentifiersPacket");
        }
        $idList->push(CompoundTag::create()->setString("id", $entityIdentifier));
        $availableActorIdentifiersPacket->identifiers = new CacheableNbt($identifiersNbt);
    }
}