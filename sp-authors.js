function ChooseMe(droplist,field) {
	if (field.value=='')
		field.value += droplist.options[droplist.selectedIndex].text;
	else
		field.value += ', ' + droplist.options[droplist.selectedIndex].text;
}