<?php 
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("NOT_SHOW_NAV_CHAIN", "Y");
$APPLICATION->SetTitle("Импорт секций инфоблока");

if(CModule::IncludeModule("iblock")) {
	$arIBlocks = [];
	$rsIBlocks = CIBlock::GetList(["sort" => "asc"], ["ACTIVE" => "Y"]);
	while($arIBlock = $rsIBlocks->Fetch())
	{
		$arIBlocks[] = ["ID" => $arIBlock["ID"], "NAME" => $arIBlock["NAME"], "IBLOCK_TYPE" => $arIBlock["IBLOCK_TYPE_ID"]];
	}
}

if(strlen($_POST["list"]) > 0) $arStr = explode("\n", $_POST["list"]);

$IBlockID = intval($_POST["iblock"]);;

$lastLevel = 1000;
$arLevels = [];
$errorTrigger = false;

if(count($arStr) > 0 && $IBlockID > 0) {
	$arStr[0] = TrimEx(trim($arStr[0]), "-", "left"); // первый элемент должен быть на 0м уровне
	$bs = new CIBlockSection;
	$arFields = [
	  "ACTIVE" => "Y",
	  "IBLOCK_ID" => $IBlockID,
	  ];	
	foreach($arStr as $strItem) {
		$level = 0; // текущий уровень вложености
		$strItem = trim($strItem);
		while (substr($strItem, 0, 1) == '-') { // определяем уровень по числу "-", заодно удаляем их
			$level++;
			$strItem = substr($strItem, 1);
		}
		$arFields["NAME"] = $strItem; // имя в массив

		//Транслитерируем имя
		$translitName =  Cutil::translit($strItem, "ru", ["replace_space" => "-", "replace_other" => "-"]);
		
		// Если такой же код есть допишем ему номер
		$countDiffCode = CIBlockSection::GetList([], ['CODE' => $translitName])->SelectedRowsCount();
		if ($countDiffCode > 0) $translitName .= $countDiffCode + 1;
		
		$arFields["CODE"] = $translitName; // символьный код

		if($level == 0) { // уровень вложенности 0 - нет родительской секции
			$lastLevel = 0;
		}
		elseif($level > $lastLevel) { // текущей уровень больше прошлого, идем вглубь структуры
			if($arLevels[$lastLevel]) {// если есть корректный предок, то заносим в поля. Иначе - будет в корне
				$arFields["IBLOCK_SECTION_ID"] = $arLevels[$lastLevel];
			}
			$lastLevel = $lastLevel + 1;
		
			if($arLevels[$level - 1]) {
				$arFields["IBLOCK_SECTION_ID"] = $arLevels[$level - 1];
			}
			$lastLevel = $level;
		}

		$lastID = $bs->Add($arFields, false);
		unset($arFields["IBLOCK_SECTION_ID"]);
		if ($lastID > 0) {
			$arLevels[$lastLevel] = $lastID;
		}
		else {
			unset($arLevels[$lastLevel]);
			echo '<span style="color:#ff0000">'.$strItem.'</span><br />';
			$errorTrigger = true;
		}

	}
	CIBlockSection::ReSort($IBlockID); // второй параметр функции Add =false, поэтому необходимо сделать ReSort

	if(!$errorTrigger) echo '<span style="color:#009900">Секции успешно созданы</span><br /><br />';
}
?>
<p>Выпадающий список содержит все инфоблоки системы в виде Тип инфоблока – Название инфоблока, среди которых нужно выбрать необходимый для заполнения.</p>

<p>Текстовое поле предназначено для ввода названий секций. Одна строка – одна секция. Вложенность реализуется с помощью символов «-» перед названиями. Например, строки</p>

Колбаса<br>
-Докторская<br>
—Свежая<br>
—Просроченная<br>
-Любительская<br>
Сыр<br>
<p>соотетствуют разделам 1го уровня «Сыр» и «Колбаса», в «Колбасу» вложены «Докторская» и «Любительская», в «Докторской» есть разделы «Свежая» и «Просроченная».</p>
<form action="/section_import.php" method="post">
	<select name="iblock" style="width:300px;">
	<?php foreach($arIBlocks as $arItem):?>
        <option value="<?=$arItem["ID"]?>"<?if(intval($_POST["iblock"])==$arItem["ID"]) echo 'selected="selected"';?>><?=$arItem["IBLOCK_TYPE"]?> - <?=$arItem["NAME"]?></option>
	<?php endforeach; ?>
	</select><br />
	<textarea name="list" style="width:300px; height:150px;"><?php if($_POST["list"]) echo $_POST["list"];?></textarea><br />
	<input type="submit" value="Создать разделы" />
</form>
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
