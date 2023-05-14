import React from "react";

import {useStateValue} from "../../Utils/StateProvider";

function RabbisFile() {

    const [stateValue, dispatch] = useStateValue();

    return ( <div> Rabbis File </div>)
}
export default RabbisFile;