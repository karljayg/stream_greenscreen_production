const playerList = [
	['Alisaunder', 'Alisaunder.mp4', 'Alisaunder.wav'],
	['Anito', 'Anito.mp4', 'Anito.wav'],
	['AngrySpaceHares', 'angry_space_hares.mp4', 'angry_space_hares.wav', 'noTitle'],
	['Archnog', 'Archnog.mp4', 'Archnog.wav'],
	['Artanis_ph', 'PSL_Artanis_ph.mp4', 'cloak.wav'],
	['Asero2', 'PSL_Asero2.mp4', 'freeden.wav'],
	['Astrea', 'PSL_Astrea.mp4', ''],
	['AxD', 'AxD.mp4', 'axd1987.wav'],
	['Azure', 'Azure.mp4', ''],
	['Azzin', 'PSL_Azzin.mp4', 'freeden.wav'],
	['BENZEL', 'BENZEL_logo.mp4', 'benzel.wav'],
	['Bayagster', 'PSL_Bayagster.mp4', 'freeden.wav'],
	['Bistork', 'Bistork.mp4', ''],
	['CYAN', 'CYAN.mp4', 'CYAN.wav'],
	['CaptBritain', 'PSL_CaptBritain.mp4', 'freeden.wav'],
	['Casper631', 'Casper631.mp4', 'Casper631.wav'],
	['Cham', 'PSL_Cham.mp4', ''],
	['CheesyNachos', 'cheesy_nachos.mp4', 'cheesy_nachos.wav', 'noTitle'],
	['ChienPwn', 'ChienPwn.mp4', 'ChienPwn.wav'],
	['ChurchillDentist', 'ChurchillDentist.mp4', 'ChurchillDentist.wav'],
	['Cloak', 'LuckyTiger.mp4', 'cloak.wav'],
	['Coheeder', 'PSL_Coheeder.mp4', ''],
	['Cypem', 'PSL_Cypem.mp4', 'freeden.wav'],
	['DB', 'DB_20200820_191155_1.mp4', 'db.wav'],
	['DLSMIZEL', 'DLSMIZEL2.mp4', 'DLSMIZEL.wav'],
	['DarkMenace', 'DarkMenaceNew.mp4', 'darkmenace.wav'],
	['DavidBane', 'PSL_DavidBane.mp4', 'freeden.wav'],
	['Deneb', 'PSL_Deneb.mp4', 'cloak.wav'],
	['EnDerr', 'PSL_EnDerr2.mp4', ''],
	['ExoDash', 'PSL_ExoDash.mp4', ''],
	['Exoblazer', 'PSL_Exoblazer.mp4', 'freeden.wav'],
	['ETPwnHome', 'etpwnhome.mp4', 'etpwnhome.wav'],
	['FSL intro', 'FSL-logo_FINAL_lower_volume.mp4', 'you_are_watching_FSL.wav', 'noTitle'],
	['Floki', 'PSL_Floki.mp4', 'freeden.wav'],
	['ForJumy', 'ForJumy.mp4', ''],
	['GG', 'transparent_greenscreen.mp4', 'FSL_GG.mp3', 'noTitle'],
	['GG_Izzy', 'GG_Izzy.mp4', 'gg_izzy.wav'],
	['Greempire', 'greempire.mp4', 'greempire.wav'],
	['Haki', 'Haki.mp4', 'Haki.wav'],
	['Harouz', 'Harouz.mp4', 'Harouz.wav'],
	['HighCommand', 'HighCommand2.mp4', 'HighCommand.wav'],
	['Hiro', 'PSL_Hiro.mp4', ''],
	['Holden', 'Holden.mp4', ''],
	['HurtnTime', 'PSL_hurtntime.mp4', 'HurtnTime.wav'],
	['HyperTurtle', 'HyperTurtle.mp4', 'HyperTurtle.wav'],
	['Imburnal', 'PSL_Imburnal.mp4', 'freeden.wav'],
	['InfiniteCyclists', 'infinite_cyclists.mp4', 'infinite_cyclists.wav', 'noTitle'],
	['Instability', 'Instability.mp4', 'Instability.wav'],
	['JDR', 'PSL_JDR.mp4', 'cloak.wav'],
	['Kas', 'PSL_Kas.mp4', ''],
	['KittyCatGamer', 'kittycatgamer.mp4', 'kittycatgamer.wav'],
	['Klaw', 'PSL_Klaw.mp4', 'freeden.wav'],
	['Kriminal', 'Kriminal_FSL.mp4', 'Kriminal.wav'],
	['LAS_logo', 'LAS_logo.mp4', 'LAS.wav'],
	['LightHood', 'lighthood2.mp4', 'LightHood.wav'],
	['LittleReaper', 'littlereaper_intro5.mp4', 'littlereaper.wav'],
	['LuckyLarry', 'LuckyLarry.mp4', 'LuckyLarry.wav'],
	['Match GG', 'transparent_greenscreen.mp4', 'PSL_GG.mp3', 'gifPlayer'],
	['MemesDreams', 'MemesDreams.mp4', 'MemesDreams.wav'],
	['Meomaika2', 'Meomaika2.mp4', ''],
	['Migu3la', 'migu3la_2023_final.mp4', 'migu3la.wav'],
	['MrLasti', 'PSL_MrLasti.mp4', 'freeden.wav'],
	['MrsUberXL', 'mrsuberxl.mp4', 'mrsuberxl.wav'],
	['Nachoz', 'Nachoz.mp4', 'Nachoz.wav'],
	['NeO', 'NeO.mp4', ''],
	['Neutrophil', 'edmund_IMG_2967.mp4', 'neutrophil.wav'],
	['Nightmare', 'PSL_Nightmare.mp4', ''],
	['Nooblord', 'nooblord.mp4', 'nooblord.wav'],
	['Nuks2', 'PSL_Nuks2.mp4', 'db.wav'],
	['Pangz', 'PSL_Pangz.mp4', 'cloak.wav'],
	['PanicSwitch', 'PanicSwitch.mp4', 'PanicSwitch.wav'],
	['Pebble', 'pebble.mp4', 'pebble.wav'],
	['PhyNixSonski2', 'PSL_PhyNixSonski2.mp4', 'freeden.wav'],
	['PlantQueen', 'FSL_PlantQueen.mp4', 'PlantQueen.wav'],
	['Pobbes', 'PSL_Pobbes.mp4', 'cloak.wav'],
	['Portrial', 'PSL_Portrial.mp4', 'freeden.wav'],
	['PulledTheBoys', 'pulled_the_boys.mp4', 'pulled_the_boys.wav', 'noTitle'],
	['PvP', 'PvP.webm', ''],
	['RagesRaiders', 'rages_raiders.mp4', 'rages_raiders.wav', 'noTitle'],
	['Random Music', 'transparent_greenscreen.mp4', '', 'randomAudio'],
	['Ranger', 'PSL_Ranger.mp4', ''],
	['RevenantRage', 'RevenantRage.mp4', 'RevenantRage.wav'],
	['Rixilius', 'PSL_Rixilius.mp4', 'freeden.wav'],
	['SEMPER', 'PSL_SEMPER.mp4', 'sef.wav'],
	['Sef', 'sef.mp4', 'sef.wav'],
	['Shadow', 'PSL_Shadow.mp4', ''],
	['SirMalagant', 'sirmalagant.mp4', 'sirmalagant.wav'],
	['SouLeer', 'PSL_SouLeer.mp4', ''],
	['SpeCial', 'PSL_SpeCial.mp4', ''],
	['SteelHeart', 'PSL_SteelHeart.mp4', 'freeden.wav'],
	['Toirtoise&Hare', 'toirtoiseandhare.mp4', 'tortoiseandhare.wav'],
	['TyphoonFusion', 'typhoonfusion.mp4', 'typhoonfusion.wav'],
	['Uriel', 'PSL_Uriel.mp4', 'freeden.wav'],
	['Vales', 'vales.mp4', 'vales.wav'],
	['VeryCool', 'VeryCool2.mp4', 'verycool.wav'],
	['Viole', 'PSL_Viole.mp4', 'freeden.wav'],
	['Warbunnies', 'Warbunnies.mp4', 'warbunnies.wav'],
	['Wheva', 'Wheva.mp4', 'wheva.wav'],
	['Xenos', 'PSL_Xenos.mp4', 'freeden.wav'],
	['XtinC', 'PSL_XtinC.mp4', 'mightyant.wav'],
	['ZvZ', 'ZvZ.webm', 'ZvZ.wav'],
	['caliberC', 'caliberC.mp4', 'caliberc.wav'],
	['chumly', 'FSL_chumly.mp4', 'chumly.wav'],
	['dpoo', 'spaghettio.mp4', 'dpoo.wav'],
	['fenrir', 'FSL_fenrir.mp4', 'fenrir.wav'],
	['fluffy', 'fluffy.mp4', 'fluffy.wav'],
	['freeden', 'freeden.mp4', 'freeden.wav'],
	['freeedom', 'KJ_barong.mp4', 'kjfreeedom.wav'],
	['ghostchant', 'FSL_ghostchant.mp4', 'ghostchant.wav'],
	['goblin', 'PSL_goblin.mp4', ''],
	['gogojoey', 'PSL_gogojoey.mp4', ''],
	['grey', 'grey.mp4', 'grey.wav'],
	['intro_sloza2', 'intro_sloza2.mp4', 'mightyant.wav'],
	['TheArchaic', 'jacob_VID_20200820_214139.mp4', 'thearchaic.wav'],
	['jmpz', 'jmpz.mp4', 'jmpz.wav'],
	['meme - No GG', 'transparent_greenscreen.mp4', 'PARTING- No GG (TRAP MIX) - short.mp3', 'gifPlayer-1'],
	['meme - Titanic', 'transparent_greenscreen.mp4', '40secs_My Heart Will Go On (Love Theme from Titanic).mp3'],
	['meme - Victory', 'transparent_greenscreen.mp4', 'paula_white_victory_short.mp3', 'gifPlayer-26'],
	['meme - all I do is Win', 'transparent_greenscreen.mp4', '15sec_DJ Khaled All I Do Is Win feat. Ludacris, Rick Ross, T-Pain & Snoop Dogg Victory In Stores N.mp3', 'gifPlayer-4'],
	['meme - braveheart', 'transparent_greenscreen.mp4', 'short_Braveheart (39) Movie CLIP - They Will Never Take Our Freedom (1995) HD.mp3', 'gifPlayer-7'],
	['meme - coffin dance', 'transparent_greenscreen.mp4', 'short_Coffin Dance (Official Music Video HD).mp3', 'gifPlayer-3'],
	['meme - high ground', 'transparent_greenscreen.mp4', '10sec_Highground_Obi-Wan vs Anakin - Duel on Mustafar (Part 2) Star Wars Revenge of the Sith (2005) Movie Clip.mp3', 'gifPlayer-2'],
	['meme - rocky', 'transparent_greenscreen.mp4', 'short_1.15pct_speed_The Mandalorian Theme X Rocky Theme (EPIC HIP HOP REMIX).mp3', 'gifPlayer-6'],
	['nice', 'PSL_nice.mp4', ''],
	['regret', 'regret2b.mp4', 'regret.wav'],
	['rex', 'FSL_rex.mp4', 'rex.wav'],
	['ronan', 'ronan.mp4', 'tlmnronan.wav'],
	['ruff', 'PSL_ruff.mp4', ''],
	['stublu88', 'stublu88.mp4', 'stublu88.wav'],
	['zzzzzz - last placeholder', '', '']
];

export default playerList;
