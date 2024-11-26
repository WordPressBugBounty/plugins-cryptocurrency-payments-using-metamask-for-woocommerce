import React, { useState, useEffect } from "react";
import Connection from "./WalletConnectors";
import Select from "react-select";
import { importNetworkById, Loader } from "../component/helper";
import { restApiGetNetworks,restUpdatePrice } from "../component/handelRestApi";

export default function ShowCurrency() {
  const { enabledCurrency, const_msg, currency_lbl,decimalchainId ,active_network, total_price} = connect_wallts;


  const [currentActiveNetwork, setCurrentActiveNetwork] = useState(null);
  const [selectedOption, setSelectedOption] = useState(null);
  const [enabledCurrencys, setEnabledCurrency] = useState(null);
  const [currencyChange, setCurrencyChange] = useState(false);
  const [networkresponse, setNetworkResponse] = useState(false);

  const selectedGateway = document.querySelector(
    'input[name="payment_method"]:checked'
  )?.value;

  const placeOrderButton = document.querySelector("button#place_order");
   
  useEffect(() => {  
  
    if(total_price){       
 
     updateNewPrice(Number(total_price))    
   
   } 
 }, [total_price]);

  useEffect(() => {
    if (selectedGateway === "cpmw") {
      placeOrderButton.disabled = true;
    } else {
      placeOrderButton.disabled = false;
    }
  }, [selectedGateway]);

  useEffect(() => {
    createpriceOptions(enabledCurrency)
  }, [enabledCurrency]);
  const updateNewPrice = async (Currentprice) => {
    try {
      const response = await restUpdatePrice(Currentprice, connect_wallts);
      createpriceOptions(response)     
    
    } catch (error) {
      console.error("Error fetching data:", error);
    }
  };

  const createpriceOptions = async (enabledCurrency) => {
    let currency = [];
  
    if (enabledCurrency.length !== 0) {
      Object.values(enabledCurrency).forEach((value, index) => {
        if (!value.price) {
          return;
        }
        const chainData = {
          value: value.symbol,
          label: (
            <span key={`cpmw_logos_${index}`} className="cpmw_logos">
              <img
                key={`cpmw_logo_${index}`}
                src={value.url}
                alt={value.symbol}
                style={{ width: "auto", height: "28px" }}
              />{" "}
              {value.price} {value.symbol}
            </span>
          ),
          rating: value.price,
        };
        currency.push(chainData);
      });
      setEnabledCurrency(currency);
    }
  };
  
  //Handel currency change event
  const handleCurrencyChange = async(event) => {
    setCurrencyChange(true);
    setSelectedOption(event);
   // GenerateNetworkHtml(event.value);    
    const res = await importNetworkById(decimalchainId);
    const response = await restApiGetNetworks(event.value, connect_wallts); 

    setNetworkResponse(response);  
    setCurrentActiveNetwork(res);  
    setCurrencyChange(false);
  };


  return (
    <div key="show_currency_wrapper">
      {enabledCurrencys ? (
        <>
          <div key={currency_lbl} className="cpmwp_currency_lbl">
            {currency_lbl}
          </div>
          <Select
            name="cpmwp_crypto_coin"
            value={selectedOption}
            onChange={handleCurrencyChange}
            options={enabledCurrencys}
            placeholder={const_msg.select_cryptocurrency}
          />
          <input
            key={active_network}
            type="hidden"
            name="cpmw_payment_network"
            value={active_network}
          />
          {currencyChange && <Loader loader={1} width={250} />}
          {!currencyChange && currentActiveNetwork && (
            <Connection
              networks={currentActiveNetwork}
              currentprice={selectedOption}
              networkResponse={networkresponse}
            />
          )}
        </>
      ) : (
        <Loader loader={1} width={250} />
      )}
    </div>
  );
}
