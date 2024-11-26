// Importing necessary libraries and components
import React, { useState, useEffect } from "react";
import Connection from "./WalletConnectors";
import Select from "react-select";
import { importNetworkById, Loader } from "../component/helper";
import { restApiGetNetworks,restUpdatePrice } from "../component/handelRestApi";

// Main function component
export default function ShowCurrency({ eventRegistration, emitResponse, billing}) {
  // Getting settings from window object
  const settings = window.wc.wcSettings.getPaymentMethodData('cpmw');
  const { onPaymentSetup } = eventRegistration;

  // Destructuring settings
  const {
    enabledCurrency,
    const_msg,
    currency_lbl,
    decimalchainId,
    active_network    
  } = settings;

  // State variables
  const [currentActiveNetwork, setCurrentActiveNetwork] = useState(null);
  const [selectedOption, setSelectedOption] = useState(null);
  const [enabledCurrencys, setEnabledCurrency] = useState(null);
  const [currencyChange, setCurrencyChange] = useState(false);
  const [networkresponse, setNetworkResponse] = useState(false);
  const [currenctBalance, setcurrenctBalance] = useState();
  const [walletConnected, setwalletConnected] = useState(false);

  // Function to format currency
  const formatCurrency = (value, currency) => {    
    const { minorUnit } = currency;
    const formattedValue = (value / Math.pow(10, minorUnit)).toFixed(minorUnit);
    return formattedValue;
  };

  // Getting numeric part of the billing
  const numericPart = formatCurrency(billing.cartTotal.value,billing.currency);

  // Effect hook to update price when numeric part changes
  useEffect(() => {  
    if(numericPart){       
      updateNewPrice(Number(numericPart))    
    } 
  }, [numericPart]);

  // Effect hook to create price options when enabledCurrency changes
  useEffect(() => {
    createpriceOptions(enabledCurrency)
  }, [enabledCurrency]);

  // Function to get balance of active network
  const getBalanceofActiveNetwork = (balance, connected) => {
    setcurrenctBalance(balance && balance.formatted);
    setwalletConnected(connected);
  }; 
  // Effect hook to handle payment setup
  useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
     
      // Various checks before proceeding with payment
      if (!selectedOption?.value) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: const_msg.required_currency,
        };
      } else if (!active_network) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: const_msg.required_network_check,
        };
      }      
      else if (!walletConnected) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: const_msg.connect_wallet,
        };
      } 
      else if (parseFloat(currenctBalance) < parseFloat(selectedOption?.rating)) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: const_msg.insufficent,
        };
      }

      // If balance and wallet are connected, return success
      if (currenctBalance && walletConnected) {
        return {
          type: emitResponse.responseTypes.SUCCESS,
          meta: {
            paymentMethodData: {
              current_balance: currenctBalance,
              cpmwp_crypto_coin: selectedOption.value,
              cpmw_payment_network: active_network,
              cpmwp_crypto_wallets: "ethereum",
            },
          },
        };
      }

      // Default error return
      return {
        type: emitResponse.responseTypes.ERROR,
        message: "Something went wrong",
      };
    });

    // Cleanup function
    return () => {
      unsubscribe();
    };
  }, [
    emitResponse.responseTypes.ERROR,
    emitResponse.responseTypes.SUCCESS,
    onPaymentSetup,
    currenctBalance,
    walletConnected,
    selectedOption,
    active_network,
  ]);

  // Function to update new price
  const updateNewPrice = async (Currentprice) => {
    try {
      const response = await restUpdatePrice(Currentprice, settings);
      createpriceOptions(response)     
    } catch (error) {
      console.error("Error fetching data:", error);
    }
  };

  // Function to create price options
  const createpriceOptions = async (enabledCurrency) => {
    let currency = [];

    if (enabledCurrency.length !== 0) {
      Object.values(enabledCurrency).forEach((value) => {
        if (!value.price) {
          return;
        }
        const chainData = {
          value: value.symbol,
          label: (
            <span className="cpmw_logos">
              <img
                key={value.symbol}
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

  // Function to handle currency change
  const handleCurrencyChange = async (event) => {
    setCurrencyChange(true);
    setSelectedOption(event);
    const res = await importNetworkById(decimalchainId);
    const response = await restApiGetNetworks(event.value, settings);

    setNetworkResponse(response);
    setCurrentActiveNetwork(res);
    setCurrencyChange(false);
  };

  // Return statement
  return (
    enabledCurrencys ? (
      <div className="cpmwp-supported-wallets-wrap">
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

        {currencyChange && <Loader loader={1} width={250} />}
        {!currencyChange && currentActiveNetwork && (
          <Connection          
            getbalance={getBalanceofActiveNetwork}
            networks={currentActiveNetwork}
            currentprice={selectedOption}
            networkResponse={networkresponse}
          />
        )}
      </div>
    ) : (
      <Loader loader={1} width={250} />
    )
  );
}
