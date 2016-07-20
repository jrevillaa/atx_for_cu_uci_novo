// Javascript functions for ucicactivity course format

M.course = M.course || {};

M.course.format = M.course.format || {};


M.course.format.get_config = function() {
    return {
        container_node : 'ul',
        container_class : 'ucicactivity',
        section_node : 'li',
        section_class : 'section'
    };
}


M.course.format.swap_sections = function(Y, node1, node2) {
    var CSS = {
        COURSECONTENT : 'course-content',
        SECTIONADDMENUS : 'section_add_menus'
    };

    var sectionlist = Y.Node.all('.'+CSS.COURSECONTENT+' '+M.course.format.get_section_selector(Y));

    sectionlist.item(node1).one('.'+CSS.SECTIONADDMENUS).swap(sectionlist.item(node2).one('.'+CSS.SECTIONADDMENUS));
}


M.course.format.process_sections = function(Y, sectionlist, response, sectionfrom, sectionto) {
    var CSS = {
        SECTIONNAME : 'sectionname'
    },
    SELECTORS = {
        SECTIONLEFTSIDE : '.left .section-handle img'
    };

    if (response.action == 'move') {
        if (sectionfrom > sectionto) {
            var temp = sectionto;
            sectionto = sectionfrom;
            sectionfrom = temp;
        }

        var ele, str, stridx, newstr;

        for (var i = sectionfrom; i <= sectionto; i++) {
            // Update section title.
            var content = Y.Node.create('<span>' + response.sectiontitles[i] + '</span>');
            sectionlist.item(i).all('.'+CSS.SECTIONNAME).setHTML(content);

            // Update move icon.
            ele = sectionlist.item(i).one(SELECTORS.SECTIONLEFTSIDE);
            str = ele.getAttribute('alt');
            stridx = str.lastIndexOf(' ');
            newstr = str.substr(0, stridx +1) + i;
            ele.setAttribute('alt', newstr);
            ele.setAttribute('title', newstr); // For FireFox as 'alt' is not refreshed.

            // Remove the current class as section has been moved.
            sectionlist.item(i).removeClass('current');
        }
        // If there is a current section, apply corresponding class in order to highlight it.
        if (response.current !== -1) {
            // Add current class to the required section.
            sectionlist.item(response.current).addClass('current');
        }
    }
}
