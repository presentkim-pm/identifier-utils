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
 */

declare(strict_types=1);

namespace kim\present\utils\identifier;

use Closure;
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

use function strrpos;
use function substr;

final class IdentifierUtils{
    private static int $itemRuntimeId = 1;

    private function __construct(){ }

    /**
     * Gets the next item Runtime ID based on not registered in ItemTranslator
     *
     * @see ItemTranslator::simpleNetToCoreMapping
     * @see ItemTranslator::complexNetToCoreMapping
     */
    public static function nextItemRuntimeId() : int{
        $runtimeId = null;
        $itemTranslator = ItemTranslator::getInstance();
        while($runtimeId === null){
            if(self::$itemRuntimeId >= 0xffff){
                throw new OverflowException("There are no more item runtime ids available.");
            }
            $cursor = self::$itemRuntimeId++;

            $runtimeId = Closure::bind( //HACK: Closure bind hack to access inaccessible members
                closure: static fn(ItemTranslator $tr) => !isset($tr->simpleNetToCoreMapping[$cursor]) && !isset($tr->complexNetToCoreMapping[$cursor]) ? $cursor : null,
                newThis: null,
                newScope: ItemTranslator::class
            )($itemTranslator);
        }
        return $runtimeId;
    }

    /**
     * Returns a namespace removed identifier.
     * ex) minecraft:diamond => diamond
     */
    public static function removeNamespace(string $stringId) : string{
        return substr($stringId, strrpos($stringId, ":") + 1);
    }

    public static function registerItem(string $stringId, int $legacyId, int $legacyMeta = -1) : void{
        /** Mapping String ID and Legacy ID to StringToItemParser and LegacyStringToItemParser */
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
            $simpleStringId = self::removeNamespace($stringId);
            $legacyStringToItemParser->addMapping($stringId, $legacyId);
            $legacyStringToItemParser->addMapping((string) $legacyId, $legacyId);
            $legacyStringToItemParser->addMapping($simpleStringId, $legacyId);
        })();

        $runtimeId = self::nextItemRuntimeId();
        /** Cross-Mapping Runtime ID and Legacy ID to ItemTranslator */
        Closure::bind( //HACK: Closure bind hack to access inaccessible members
            closure: static function(ItemTranslator $translator) use ($runtimeId, $legacyId, $legacyMeta){
                if($legacyMeta === -1){
                    // simple mapping - When the same item has multiple meta. ex) IronHoe
                    $translator->simpleCoreToNetMapping[$legacyId] = $runtimeId;
                    $translator->simpleNetToCoreMapping[$runtimeId] = $legacyId;
                }else{
                    // complex mapping - When items are classified according to meta. ex) Bucket
                    $translator->complexCoreToNetMapping[$legacyId][$legacyMeta] = $runtimeId;
                    $translator->complexNetToCoreMapping[$runtimeId] = [$legacyId, $legacyMeta];
                }
            },
            newThis: null,
            newScope: ItemTranslator::class
        )(ItemTranslator::getInstance());

        /** Cross-Mapping Runtime ID and String ID to ItemTypeDictionary */
        Closure::bind( //HACK: Closure bind hack to access inaccessible members
            closure: static function(ItemTypeDictionary $dictionary) use ($stringId, $runtimeId){
                $dictionary->itemTypes[] = new ItemTypeEntry($stringId, $runtimeId, true);
                $dictionary->stringToIntMap[$stringId] = $runtimeId;
                $dictionary->intToStringIdMap[$runtimeId] = $stringId;
            },
            newThis: null,
            newScope: ItemTypeDictionary::class
        )(GlobalItemTypeDictionary::getInstance()->getDictionary());
    }

    public static function registerEntity(string $stringId) : void{
        $availableActorIdentifiersPacket = StaticPacketCache::getInstance()->getAvailableActorIdentifiers();
        /** @var CompoundTag $identifiersNbt */
        $identifiersNbt = $availableActorIdentifiersPacket->identifiers->getRoot();
        $identifiersNbt->getListTag("idlist")?->push(CompoundTag::create()->setString("id", $stringId));
        $availableActorIdentifiersPacket->identifiers = new CacheableNbt($identifiersNbt);
    }
}