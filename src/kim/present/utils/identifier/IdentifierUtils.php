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
 * @noinspection PhpDeprecationInspection
 * @noinspection PhpUndefinedFieldInspection
 */

declare(strict_types=1);

namespace kim\present\utils\identifier;

use OverflowException;
use pocketmine\item\ItemFactory;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use RuntimeException;

use function strrpos;
use function substr;

final class IdentifierUtils{
    private static int $itemRuntimeId = 1;

    private function __construct(){ }

    public static function registerItem(string $stringId, int $legacyId, int $legacyMeta = -1) : void{
        /**
         * Mapping String ID and Legacy ID to StringToItemParser and LegacyStringToItemParser
         * that used to parse a string or ID to get an item object.
         *
         * @see StringToItemParser::override()
         * @see LegacyStringToItemParser::addMapping()
         */
        (static function() use ($stringId, $legacyId, $legacyMeta){
            $stringToItemParser = new StringToItemParser();
            if($legacyMeta === -1){
                $stringToItemParser->override($stringId, static fn() => ItemFactory::getInstance()->get($legacyId));
            }else{
                $getItemCallback = static fn() => ItemFactory::getInstance()->get($legacyId, $legacyMeta);
                $stringToItemParser->override($stringId, $getItemCallback);
                $stringToItemParser->override("$stringId:$legacyMeta", $getItemCallback);
            }
            $legacyStringToItemParser = LegacyStringToItemParser::getInstance();
            $simpleStringId = substr($stringId, strrpos($stringId, ':') + 1);
            $legacyStringToItemParser->addMapping($stringId, $legacyId);
            $legacyStringToItemParser->addMapping((string) $legacyId, $legacyId);
            $legacyStringToItemParser->addMapping($simpleStringId, $legacyId);
        })();

        /**
         * Gets the item Runtime ID to use for the item currently being added
         * Based on not registered in ItemTranslator
         *
         * @see ItemTranslator::simpleNetToCoreMapping
         * @see ItemTranslator::complexNetToCoreMapping
         */
        $runtimeId = null;
        (static function() use (&$runtimeId){
            $itemTranslator = ItemTranslator::getInstance();
            while($runtimeId === null){
                if(self::$itemRuntimeId >= 0xffff){
                    throw new OverflowException("There are no more item runtime ids available.");
                }
                $cursor = self::$itemRuntimeId++;
                $runtimeId = (function() use ($cursor){ //HACK : Closure bind hack to access inaccessible members
                    return !isset($this->simpleNetToCoreMapping[$cursor]) && !isset($this->complexNetToCoreMapping[$cursor]) ? $cursor : null;
                })->call($itemTranslator);
            }
        })();

        /**
         * Cross-Mapping Runtime ID and Legacy ID to ItemTranslator
         *
         * @see ItemTranslator::simpleCoreToNetMapping
         * @see ItemTranslator::simpleNetToCoreMapping
         * @see ItemTranslator::complexCoreToNetMapping
         * @see ItemTranslator::complexNetToCoreMapping
         */
        (function() use ($runtimeId, $legacyId, $legacyMeta){ //HACK : Closure bind hack to access inaccessible members
            if($legacyMeta === -1){
                // simple mapping - When the same item has multiple meta. ex) IronHoe
                $this->simpleCoreToNetMapping[$legacyId] = $runtimeId;
                $this->simpleNetToCoreMapping[$runtimeId] = $legacyId;
            }else{
                // complex mapping - When items are classified according to meta. ex) Bucket
                $this->complexCoreToNetMapping[$legacyId][$legacyMeta] = $runtimeId;
                $this->complexNetToCoreMapping[$runtimeId] = [$legacyId, $legacyMeta];
            }
        })->call(ItemTranslator::getInstance());

        /**
         * Cross-Mapping Runtime ID and String ID to ItemTypeDictionary
         *
         * @see ItemTypeDictionary::itemTypes
         * @see ItemTypeDictionary::stringToIntMap
         * @see ItemTypeDictionary::intToStringIdMap
         */
        (function() use ($stringId, $runtimeId){ //HACK : Closure bind hack to access inaccessible members
            $this->itemTypes[] = new ItemTypeEntry($stringId, $runtimeId, true);
            $this->stringToIntMap[$stringId] = $runtimeId;
            $this->intToStringIdMap[$runtimeId] = $stringId;
        })->call(GlobalItemTypeDictionary::getInstance()->getDictionary());
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